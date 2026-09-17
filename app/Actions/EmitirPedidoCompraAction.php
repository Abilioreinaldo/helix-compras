<?php

namespace App\Actions;

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Mail\PedidoCompraEmitido;
use App\Models\Cotacao;
use App\Models\ItemCotacao;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Models\User;
use App\Support\SequenciaAnualPorTenant;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Helix\Foundation\Services\Platform\Support\TenantContext;
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
            // Lock pessimista: evita emissão dupla concorrente (e pagamento duplicado).
            // withoutGlobalScopes: o pedido já foi autorizado pelo chamador; o lock é por PK.
            $pedido = PedidoCompra::withoutGlobalScope(UnidadeScope::class)->lockForUpdate()->findOrFail($pedido->id);

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

            // COMPRAS-1 / COMPRAS-V1 (4ª auditoria): nada do que define dinheiro vem da tela.
            // Quantidade, item aprovado e preço cotado são RELIDOS do banco aqui, dentro da
            // transação; `valor_total` é recalculado e regravado antes de qualquer soma.
            $this->conferirItensContraAprovadoECotado($itens);

            $fornecedor = $pedido->fornecedor()->first();
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

            // Número: sequência anual POR TENANT (lock na linha do tenant — ver SequenciaAnualPorTenant).
            $ano = (int) now()->year;
            $proximo = app(SequenciaAnualPorTenant::class)->proximo('sequencias_pedido_compra', (string) $pedido->tenant_id, $ano);

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
                $requisicao = Requisicao::withoutGlobalScope(UnidadeScope::class)->find($requisicaoId);
                if ($requisicao && $requisicao->status === StatusRequisicao::Aprovada) {
                    $this->transicionar->execute(
                        $requisicao,
                        StatusRequisicao::EmCompra,
                        "Pedido de compra {$numero} emitido.",
                        true
                    );
                }
            }

            // ESCOPO (D10, ponte dual): dual-write da foundation.
            app(ActivityRecorder::class)->record('compras.pedido_emitido', $pedido, $pedido->tenant_id, [
                'actor_id' => $emissor->id,
                'metadata' => ['numero' => $numero],
            ]);

            return $pedido->fresh(['itens', 'fornecedor', 'unidade']);
        }, 3);

        $this->notificarSolicitantes($pedido);

        return $pedido;
    }

    private function notificarSolicitantes(PedidoCompra $pedido): void
    {
        $requisicaoIds = $pedido->itens->pluck('requisicao_id')->unique();

        Requisicao::withoutGlobalScope(UnidadeScope::class)
            ->whereIn('id', $requisicaoIds)
            ->with('solicitante')
            ->get()
            ->pluck('solicitante')
            ->filter()
            ->unique('id')
            ->each(fn ($sol) => Mail::to($sol)->send(new PedidoCompraEmitido($pedido)));
    }

    /**
     * Confere cada item do pedido contra o que foi APROVADO (item da requisição) e
     * COTADO (linha da cotação do próprio item), relendo tudo do banco, e regrava
     * `valor_total = quantidade × valor_unitário`.
     *
     *  - item da requisição rejeitado por linha não entra em pedido;
     *  - a quantidade do pedido não passa da quantidade aprovada do item;
     *  - o unitário do pedido não passa do unitário cotado (+ tolerância literal de
     *    `config/compras.php`, default 0). Preço menor é sempre aceito.
     *
     * @param  Collection<int, ItemPedidoCompra>  $itens
     *
     * @throws ValidationException
     */
    private function conferirItensContraAprovadoECotado(Collection $itens): void
    {
        $itensRequisicao = ItemRequisicao::whereIn('id', $itens->pluck('item_requisicao_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $cotados = ItemCotacao::whereIn('cotacao_id', $itens->pluck('cotacao_id')->filter()->unique())
            ->get()
            ->keyBy(fn (ItemCotacao $linha) => $linha->cotacao_id.':'.$linha->item_requisicao_id);

        $tolerancia = max(0.0, (float) config('compras.pedido.tolerancia_preco_cotado', 0.0));

        foreach ($itens as $item) {
            $itemRequisicao = $item->item_requisicao_id ? $itensRequisicao->get($item->item_requisicao_id) : null;

            if ($itemRequisicao !== null) {
                if ((int) $itemRequisicao->requisicao_id !== (int) $item->requisicao_id) {
                    throw ValidationException::withMessages([
                        'itens' => "O item \"{$item->descricao}\" não pertence à requisição informada.",
                    ]);
                }

                if ($itemRequisicao->rejeitado_em !== null) {
                    throw ValidationException::withMessages([
                        'itens' => "O item \"{$item->descricao}\" foi rejeitado na aprovação e não pode ser comprado.",
                    ]);
                }

                if ((float) $item->quantidade > (float) $itemRequisicao->quantidade + 0.0005) {
                    throw ValidationException::withMessages([
                        'itens' => "O item \"{$item->descricao}\" está com quantidade acima da quantidade aprovada na requisição.",
                    ]);
                }
            }

            $cotado = $cotados->get($item->cotacao_id.':'.$item->item_requisicao_id);

            if ($cotado !== null) {
                $maximo = round((float) $cotado->valor_unitario * (1 + $tolerancia), 2);

                if (round((float) $item->valor_unitario, 2) > $maximo + 0.0001) {
                    throw ValidationException::withMessages([
                        'itens' => "O item \"{$item->descricao}\" está com valor unitário (R$ ".number_format((float) $item->valor_unitario, 2, ',', '.')
                            .') acima do preço cotado (R$ '.number_format((float) $cotado->valor_unitario, 2, ',', '.').'). Registre nova cotação para comprar a esse preço.',
                    ]);
                }
            }

            $valorTotal = self::valorLinha($item);

            if (round((float) $item->valor_total, 2) !== $valorTotal) {
                $item->update(['valor_total' => $valorTotal]);
            }
        }
    }

    /** Valor da linha SEMPRE derivado: quantidade × unitário (nunca o `valor_total` persistido). */
    private static function valorLinha(ItemPedidoCompra $item): float
    {
        return round((float) $item->quantidade * (float) $item->valor_unitario, 2);
    }

    /**
     * Teto aprovado da requisição para emissão de pedidos.
     *
     * Cotação por item: soma de (unitário cotado × quantidade aprovada) SÓ dos itens não
     * rejeitados na decisão por linha (COMPRAS-V1: o gasto cortado pelo aprovador não volta).
     * Cotação legada (só valor total): o valor cheio, reduzido na proporção do valor
     * estimado dos itens rejeitados — sem preço por item não há como saber quanto cada
     * linha valia na proposta, e manter o valor cheio devolveria o corte à compradora.
     */
    private function tetoAprovado(Cotacao $cotacao, int $requisicaoId): float
    {
        $itensRequisicao = ItemRequisicao::where('requisicao_id', $requisicaoId)->get()->keyBy('id');
        $linhas = $cotacao->itensCotacao()->get();

        if ($linhas->isNotEmpty()) {
            return round($linhas->sum(function (ItemCotacao $linha) use ($itensRequisicao) {
                $itemRequisicao = $itensRequisicao->get($linha->item_requisicao_id);

                if ($itemRequisicao === null || $itemRequisicao->rejeitado_em !== null) {
                    return 0.0;
                }

                return round((float) $linha->valor_unitario * (float) $itemRequisicao->quantidade, 2);
            }), 2);
        }

        $valor = (float) $cotacao->valor;
        $rejeitados = $itensRequisicao->whereNotNull('rejeitado_em');

        if ($rejeitados->isEmpty()) {
            return $valor;
        }

        $estimado = fn (ItemRequisicao $i) => (float) $i->quantidade * (float) ($i->valor_unitario_estimado ?? 0);
        $estimadoTotal = (float) $itensRequisicao->sum($estimado);

        if ($estimadoTotal <= 0) {
            // Sem estimativa não há proporção: divide pelo número de itens (fail-closed —
            // nunca devolve o valor cheio quando houve corte por linha).
            return round($valor * ($itensRequisicao->count() - $rejeitados->count()) / max(1, $itensRequisicao->count()), 2);
        }

        return round($valor * (($estimadoTotal - (float) $rejeitados->sum($estimado)) / $estimadoTotal), 2);
    }

    /**
     * Valida que a soma de PCs emitidos + este PC não excede o teto aprovado da requisição.
     *
     * @throws ValidationException
     */
    private function validarLimiteDesmembramento(PedidoCompra $pedidoAtual, int $requisicaoId, Collection $itensDoPC): void
    {
        // Lock na requisição: dois pedidos da MESMA requisição emitidos ao mesmo tempo
        // liam o mesmo "já emitido" e passavam os dois no teto. Serializa por requisição.
        Requisicao::withoutGlobalScope(UnidadeScope::class)->lockForUpdate()->find($requisicaoId);

        $cotacao = Cotacao::where('requisicao_id', $requisicaoId)
            ->where('vencedora', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $cotacao || ! $cotacao->valor) {
            return;
        }

        $tenantId = TenantContext::requireId('teto de emissão do pedido');

        $jaEmitido = DB::table('itens_pedido_compra')
            // Query builder cru não passa pelo BelongsToTenant: recorte explícito em
            // TODA tabela do join (3ª auditoria: pedido de outro tenant com FK cruzada
            // contava no teto e bloqueava a emissão legítima).
            ->where('itens_pedido_compra.tenant_id', $tenantId)
            ->join('pedidos_compra', fn ($j) => $j
                ->on('itens_pedido_compra.pedido_compra_id', '=', 'pedidos_compra.id')
                ->where('pedidos_compra.tenant_id', $tenantId))
            ->where('itens_pedido_compra.requisicao_id', $requisicaoId)
            ->where('pedidos_compra.status', StatusPedidoCompra::Emitido->value)
            ->where('pedidos_compra.id', '!=', $pedidoAtual->id)
            ->whereNull('itens_pedido_compra.deleted_at')
            ->whereNull('pedidos_compra.deleted_at')
            // COMPRAS-1: quantidade × unitário, nunca o `valor_total` persistido (ROUND existe
            // igual em SQLite e MySQL). Pedido antigo com total adulterado conta pelo valor real.
            ->sum(DB::raw('ROUND(itens_pedido_compra.quantidade * itens_pedido_compra.valor_unitario, 2)'));

        $nestePC = $itensDoPC->where('requisicao_id', $requisicaoId)->sum(fn (ItemPedidoCompra $i) => self::valorLinha($i));

        $total = round((float) $jaEmitido + $nestePC, 2);
        $teto = $this->tetoAprovado($cotacao, $requisicaoId);

        if ($total > $teto + 0.005) {
            $codigo = Requisicao::withoutGlobalScope(UnidadeScope::class)->find($requisicaoId)?->codigo ?? "#$requisicaoId";
            throw ValidationException::withMessages([
                'desmembramento' => "Valor total dos pedidos para {$codigo} (R$ ".number_format($total, 2, ',', '.').') excede o valor aprovado (R$ '.number_format($teto, 2, ',', '.').').', ]);
        }
    }
}
