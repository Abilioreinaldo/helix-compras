<?php

namespace App\Actions;

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Mail\PedidoCompraRecebido;
use App\Models\ItemRecebimento;
use App\Models\PedidoCompra;
use App\Models\Recebimento;
use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RegistrarRecebimentoAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar,
        private readonly EntradaEstoqueAction $entradaEstoque,
    ) {}

    /**
     * @param  array<int, float>  $quantidades  item_pedido_compra_id => quantidade_recebida
     * @param  array<int, array{numero_lote?: string|null, validade?: string|null}>  $lotes  item_pedido_compra_id => dados do lote (apenas itens controla_lote)
     *
     * @throws ValidationException
     */
    public function execute(PedidoCompra $pedido, User $almoxarife, array $quantidades, ?string $observacoes = null, array $lotes = []): Recebimento
    {
        $recebimento = DB::transaction(function () use ($pedido, $almoxarife, $quantidades, $observacoes, $lotes) {
            $pedido->refresh();

            if ($pedido->status !== StatusPedidoCompra::Emitido) {
                throw ValidationException::withMessages([
                    'status' => 'Apenas pedidos emitidos podem ter recebimento registrado.',
                ]);
            }

            $itens = $pedido->itens()->get()->keyBy('id');

            $itensComQtd = collect($quantidades)->filter(fn ($qty) => (float) $qty > 0);

            if ($itensComQtd->isEmpty()) {
                throw ValidationException::withMessages([
                    'quantidades' => 'Informe ao menos uma quantidade maior que zero.',
                ]);
            }

            foreach ($itensComQtd as $itemId => $qtdNova) {
                $item = $itens->get($itemId);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'quantidades' => "Item #{$itemId} não pertence a este pedido.",
                    ]);
                }

                $jaRecebido = (float) DB::table('itens_recebimento')
                    // Query builder cru não passa pelo BelongsToTenant: recorte explícito.
                    ->where('tenant_id', TenantContext::requireId('saldo a receber do item'))
                    ->where('item_pedido_compra_id', $itemId)
                    ->whereNull('deleted_at')
                    ->sum('quantidade_recebida');

                $disponivel = (float) $item->quantidade - $jaRecebido;

                if ((float) $qtdNova > $disponivel + 0.001) {
                    throw ValidationException::withMessages([
                        'quantidades' => "Item \"{$item->descricao}\": quantidade a receber ({$qtdNova}) excede o saldo disponível (".number_format($disponivel, 3, ',', '.').').', ]);
                }
            }

            $recebimento = Recebimento::create([
                'pedido_compra_id' => $pedido->id,
                'almoxarife_id' => $almoxarife->id,
                'recebido_em' => now(),
                'observacoes' => $observacoes,
            ]);

            foreach ($itensComQtd as $itemId => $qtdNova) {
                $itemRecebimento = ItemRecebimento::create([
                    'recebimento_id' => $recebimento->id,
                    'item_pedido_compra_id' => $itemId,
                    'quantidade_recebida' => (float) $qtdNova,
                ]);

                $dadosLote = $lotes[$itemId] ?? [];

                $this->entradaEstoque->execute(
                    itemPedidoCompra: $itens->get($itemId),
                    itemRecebimento: $itemRecebimento,
                    quantidade: (float) $qtdNova,
                    registradoPor: $almoxarife,
                    numeroLote: $dadosLote['numero_lote'] ?? null,
                    validade: $dadosLote['validade'] ?? null,
                );
            }

            // Verificar conclusão por requisição
            $requisicaoIds = $itens->pluck('requisicao_id')->unique();

            foreach ($requisicaoIds as $requisicaoId) {
                $this->verificarConclusaoRequisicao($requisicaoId);
            }

            // ESCOPO (D10, ponte dual): dual-write da foundation.
            app(ActivityRecorder::class)->record('compras.recebimento_registrado', $recebimento, $pedido->tenant_id, [
                'actor_id' => $almoxarife->id,
                'metadata' => ['pedido_numero' => $pedido->numero, 'itens' => $itensComQtd->count()],
            ]);

            return $recebimento;
        }, 3);

        $this->notificarSolicitantes($recebimento);

        return $recebimento;
    }

    private function verificarConclusaoRequisicao(int $requisicaoId): void
    {
        // Item com saldo pendente: quantidade > já recebido (usando subquery para evitar fan-out)
        $tenantId = TenantContext::requireId('conclusão da requisição');

        // Mesma correção do PedidoCompra::statusRecebimento (2ª auditoria adversarial):
        // a derivada agregava `itens_recebimento` da instalação inteira antes do join.
        $recebidoPorItem = DB::table('itens_recebimento')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->groupBy('item_pedido_compra_id')
            ->select('item_pedido_compra_id', DB::raw('SUM(quantidade_recebida) as rec'));

        $pendente = DB::table('itens_pedido_compra as ipc')
            // Query builder cru não passa pelo BelongsToTenant: recorte explícito.
            ->where('ipc.tenant_id', $tenantId)
            ->join('pedidos_compra as pc', fn ($join) => $join
                ->on('ipc.pedido_compra_id', '=', 'pc.id')
                ->where('pc.tenant_id', $tenantId))
            ->leftJoinSub($recebidoPorItem, 'ir', 'ir.item_pedido_compra_id', '=', 'ipc.id')
            ->where('ipc.requisicao_id', $requisicaoId)
            ->whereIn('pc.status', [StatusPedidoCompra::Emitido->value])
            ->whereNull('ipc.deleted_at')
            ->whereNull('pc.deleted_at')
            ->whereRaw('ipc.quantidade > COALESCE(ir.rec, 0) + 0.001')
            ->exists();

        if ($pendente) {
            return;
        }

        $requisicao = Requisicao::withoutGlobalScope(UnidadeScope::class)->find($requisicaoId);

        if (! $requisicao || $requisicao->status !== StatusRequisicao::EmCompra) {
            return;
        }

        $this->transicionar->execute(
            $requisicao,
            StatusRequisicao::Recebida,
            'Todos os itens do pedido de compra foram recebidos.',
            true
        );

        $this->transicionar->execute(
            $requisicao,
            StatusRequisicao::Concluida,
            'Requisição concluída após recebimento completo.',
            true
        );
    }

    private function notificarSolicitantes(Recebimento $recebimento): void
    {
        $pedido = PedidoCompra::withoutGlobalScope(UnidadeScope::class)->with('itens')->find($recebimento->pedido_compra_id);

        if (! $pedido) {
            return;
        }

        $requisicaoIds = $pedido->itens->pluck('requisicao_id')->unique();

        Requisicao::withoutGlobalScope(UnidadeScope::class)
            ->whereIn('id', $requisicaoIds)
            ->where('status', StatusRequisicao::Concluida->value)
            ->with('solicitante')
            ->get()
            ->pluck('solicitante')
            ->filter()
            ->unique('id')
            ->each(fn ($sol) => Mail::to($sol)->send(new PedidoCompraRecebido($pedido)));
    }
}
