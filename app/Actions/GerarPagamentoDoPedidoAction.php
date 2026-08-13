<?php

namespace App\Actions;

use App\Enums\StatusPagamento;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Illuminate\Support\Carbon;

/**
 * Cria a conta a pagar (Pagamento) a partir de um Pedido de Compra emitido.
 * Idempotente: um pagamento por pedido (não duplica em reprocessamento).
 *
 * Vencimento padrão = data de emissão + 30 dias.
 */
class GerarPagamentoDoPedidoAction
{
    private const PRAZO_PAGAMENTO_DIAS = 30;

    public function execute(PedidoCompra $pedido, User $criador): Pagamento
    {
        $existente = Pagamento::where('pedido_compra_id', $pedido->id)->first();
        if ($existente !== null) {
            return $existente;
        }

        $valorTotal = round((float) $pedido->itens()->whereNull('deleted_at')->sum('valor_total'), 2);
        $emissao = $pedido->emitido_em ? Carbon::parse($pedido->emitido_em) : Carbon::now();

        $pagamento = Pagamento::create([
            'pedido_compra_id' => $pedido->id,
            'fornecedor_id' => $pedido->fornecedor_id,
            'data_emissao' => $emissao->toDateString(),
            'data_vencimento' => $emissao->copy()->addDays(self::PRAZO_PAGAMENTO_DIAS)->toDateString(),
            'valor_total' => $valorTotal,
            'valor_pago' => 0,
            'status' => StatusPagamento::Pendente,
            'criado_por' => $criador->id,
        ]);

        // ESCOPO (D10, ponte dual): dual-write da foundation.
        app(ActivityRecorder::class)->record('compras.pagamento_gerado', $pagamento, $pedido->tenant_id, [
            'actor_id' => $criador->id,
            'metadata' => ['valor_total' => $valorTotal, 'pedido_id' => $pedido->id],
        ]);

        return $pagamento;
    }
}
