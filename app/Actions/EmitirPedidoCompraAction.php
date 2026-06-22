<?php

namespace App\Actions;

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Mail\PedidoCompraEmitido;
use App\Models\Cotacao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmitirPedidoCompraAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(PedidoCompra $pedido, User $emissor): PedidoCompra
    {
        $pedido = DB::transaction(function () use ($pedido, $emissor) {
            $pedido->refresh();

            if ($pedido->status !== StatusPedidoCompra::Rascunho) {
                throw ValidationException::withMessages([
                    'status' => 'Apenas rascunhos podem ser emitidos.',
                ]);
            }

            $itens = $pedido->itens()->get();

            if ($itens->isEmpty()) {
                throw ValidationException::withMessages([
                    'itens' => 'O pedido deve ter ao menos um item.',
                ]);
            }

            foreach ($itens as $item) {
                if ((float) $item->valor_unitario <= 0) {
                    throw ValidationException::withMessages([
                        'itens' => "O item \"{$item->descricao}\" deve ter valor unitário maior que zero.",
                    ]);
                }

                if (empty($item->destino)) {
                    throw ValidationException::withMessages([
                        'itens' => "O item \"{$item->descricao}\" deve ter destino definido.",
                    ]);
                }
            }

            $fornecedor = $pedido->fornecedor()->withoutGlobalScopes()->first();
            if (! $fornecedor || ! $fornecedor->homologado || ! $fornecedor->ativo) {
                throw ValidationException::withMessages([
                    'fornecedor' => 'O fornecedor não está homologado ou ativo.',
                ]);
            }

            // Validação de desmembramento por requisição
            $requisicaoIds = $itens->pluck('requisicao_id')->unique();
            foreach ($requisicaoIds as $requisicaoId) {
                $this->validarLimiteDesmembramento($pedido, $requisicaoId, $itens);
            }

            // Gerar número com lock na sequência anual
            $ano = (int) now()->year;
            $seq = DB::table('sequencias_pedido_compra')->where('ano', $ano)->lockForUpdate()->first();

            if ($seq === null) {
                DB::table('sequencias_pedido_compra')->insertOrIgnore([
                    'ano' => $ano,
                    'ultimo_numero' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $seq = DB::table('sequencias_pedido_compra')->where('ano', $ano)->lockForUpdate()->first();
            }

            $proximo = $seq->ultimo_numero + 1;
            DB::table('sequencias_pedido_compra')->where('ano', $ano)->update([
                'ultimo_numero' => $proximo,
                'updated_at' => now(),
            ]);

            $numero = sprintf('PC-%04d-%04d', $ano, $proximo);

            $pedido->update([
                'numero' => $numero,
                'ano' => $ano,
                'sequencia' => $proximo,
                'status' => StatusPedidoCompra::Emitido,
                'emitido_em' => now(),
                'emitido_por' => $emissor->id,
            ]);

            // Gera a conta a pagar (Contas a Pagar) de forma atômica com a emissão.
            app(GerarPagamentoDoPedidoAction::class)->execute($pedido, $emissor);

            // Transicionar cada requisição vinculada para EmCompra
            foreach ($requisicaoIds as $requisicaoId) {
                $requisicao = Requisicao::withoutGlobalScopes()->find($requisicaoId);
                if ($requisicao && $requisicao->status === StatusRequisicao::Aprovada) {
                    $this->transicionar->execute(
                        $requisicao,
                        StatusRequisicao::EmCompra,
                        "Pedido de compra {$numero} emitido.",
                        true
                    );
                }
            }

            return $pedido->fresh(['itens', 'fornecedor', 'unidade']);
        }, 3);

        $this->notificarSolicitantes($pedido);

        return $pedido;
    }

    private function notificarSolicitantes(PedidoCompra $pedido): void
    {
        $requisicaoIds = $pedido->itens->pluck('requisicao_id')->unique();

        Requisicao::withoutGlobalScopes()
            ->whereIn('id', $requisicaoIds)
            ->with('solicitante')
            ->get()
            ->pluck('solicitante')
            ->filter()
            ->unique('id')
            ->each(fn ($sol) => Mail::to($sol)->send(new PedidoCompraEmitido($pedido)));
    }

    /**
     * Valida que a soma de PCs emitidos + este PC não excede o valor da cotação vencedora.
     *
     * @throws ValidationException
     */
    private function validarLimiteDesmembramento(PedidoCompra $pedidoAtual, int $requisicaoId, Collection $itensDoPC): void
    {
        $cotacaoValor = Cotacao::where('requisicao_id', $requisicaoId)
            ->where('vencedora', true)
            ->whereNull('deleted_at')
            ->value('valor');

        if (! $cotacaoValor) {
            return;
        }

        $jaEmitido = DB::table('itens_pedido_compra')
            ->join('pedidos_compra', 'itens_pedido_compra.pedido_compra_id', '=', 'pedidos_compra.id')
            ->where('itens_pedido_compra.requisicao_id', $requisicaoId)
            ->where('pedidos_compra.status', StatusPedidoCompra::Emitido->value)
            ->where('pedidos_compra.id', '!=', $pedidoAtual->id)
            ->whereNull('itens_pedido_compra.deleted_at')
            ->whereNull('pedidos_compra.deleted_at')
            ->sum('itens_pedido_compra.valor_total');

        $nestePC = $itensDoPC->where('requisicao_id', $requisicaoId)->sum(fn ($i) => (float) $i->valor_total);

        $total = (float) $jaEmitido + $nestePC;
        $teto = (float) $cotacaoValor;

        if ($total > $teto + 0.005) {
            $codigo = Requisicao::withoutGlobalScopes()->find($requisicaoId)?->codigo ?? "#$requisicaoId";
            throw ValidationException::withMessages([
                'desmembramento' => "Valor total dos pedidos para {$codigo} (R$ ".number_format($total, 2, ',', '.').') excede o valor aprovado (R$ '.number_format($teto, 2, ',', '.').').', ]);
        }
    }
}
