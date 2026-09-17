<?php

namespace App\Actions;

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CriarRascunhoPedidoAction
{
    /**
     * Cria um rascunho de PC a partir de requisições aprovadas do mesmo fornecedor.
     *
     * @param  Collection<int, Requisicao>  $requisicoes
     *
     * @throws ValidationException
     */
    public function execute(Fornecedor $fornecedor, Collection $requisicoes, User $criador): PedidoCompra
    {
        return DB::transaction(function () use ($fornecedor, $requisicoes, $criador) {
            if ($requisicoes->isEmpty()) {
                throw ValidationException::withMessages([
                    'requisicoes' => 'Selecione ao menos uma requisição.',
                ]);
            }

            foreach ($requisicoes as $requisicao) {
                if (! in_array($requisicao->status, [StatusRequisicao::Aprovada, StatusRequisicao::EmCompra])) {
                    throw ValidationException::withMessages([
                        'requisicoes' => "A requisição {$requisicao->codigo} não está aprovada.",
                    ]);
                }

                $cotacao = Cotacao::where('requisicao_id', $requisicao->id)
                    ->where('fornecedor_id', $fornecedor->id)
                    ->where('vencedora', true)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $cotacao) {
                    throw ValidationException::withMessages([
                        'requisicoes' => "A requisição {$requisicao->codigo} não tem cotação vencedora do fornecedor {$fornecedor->razao_social}.",
                    ]);
                }
            }

            $pedido = PedidoCompra::create([
                'status' => StatusPedidoCompra::Rascunho,
                'fornecedor_id' => $fornecedor->id,
                'unidade_id' => $requisicoes->first()->unidade_id,
                'criado_por' => $criador->id,
            ]);

            foreach ($requisicoes as $requisicao) {
                $cotacao = Cotacao::where('requisicao_id', $requisicao->id)
                    ->where('fornecedor_id', $fornecedor->id)
                    ->where('vencedora', true)
                    ->whereNull('deleted_at')
                    ->first();

                // Itens rejeitados na decisão por linha da aprovação ficam fora do pedido.
                $itens = $requisicao->itens()->whereNull('rejeitado_em')->get();

                // COMPRAS-V1 (4ª auditoria): o rascunho nasce com o PREÇO COTADO do item na
                // cotação vencedora — não com zero para a compradora digitar qualquer valor.
                // A emissão confere de novo (EmitirPedidoCompraAction): preço acima do cotado
                // não passa. Cotação legada (só valor total) não tem preço por item: fica 0.
                $precosCotados = $cotacao->itensCotacao()->pluck('valor_unitario', 'item_requisicao_id');

                foreach ($itens as $item) {
                    $unitario = round((float) ($precosCotados[$item->id] ?? 0), 2);

                    $pedido->itens()->create([
                        'requisicao_id' => $requisicao->id,
                        'item_requisicao_id' => $item->id,
                        'cotacao_id' => $cotacao->id,
                        'descricao' => $item->descricao,
                        'quantidade' => $item->quantidade,
                        'unidade_medida' => $item->unidade_medida,
                        'valor_unitario' => $unitario,
                        'valor_total' => round((float) $item->quantidade * $unitario, 2),
                        'destino' => null,
                        'item_catalogo_id' => $item->item_catalogo_id,
                        'avulso' => $item->avulso,
                    ]);
                }
            }

            return $pedido;
        });
    }
}
