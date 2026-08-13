<?php

declare(strict_types=1);

namespace App\Subscribers;

use App\Models\PedidoLojaRecebido;
use Helix\Foundation\Models\Platform\Event\DomainEvent;
use Helix\Foundation\Services\Platform\Event\Contracts\Subscriber;

/**
 * Consome `store.purchase_request.created` (ADR-015): grava o pedido da loja no
 * inbox `pedidos_loja_recebidos`, idempotente por tenant_id + request_code.
 *
 * O evento chega ao outbox do Compras pelo receptor inbound assinado da
 * foundation; este subscriber roda no ProcessDomainEvent, sob o TenantContext
 * do evento (o BelongsToTenant carimba/escopa o tenant automaticamente).
 *
 * NÃO cria Requisicao: a promoção é manual (mapear loja→unidade, CNPJ→fornecedor,
 * itens→catálogo). Entrega é at-least-once — a idempotência é aqui.
 */
class IngerirPedidoLoja implements Subscriber
{
    public const EVENTO = 'store.purchase_request.created';

    public function handle(DomainEvent $event): void
    {
        $p = $event->payload;

        if (empty($p['request_code'])) {
            return; // sem a chave natural não há o que ingerir (contrato inválido)
        }

        PedidoLojaRecebido::firstOrCreate(
            ['request_code' => $p['request_code']],
            [
                'store_code' => $p['store_code'] ?? null,
                'supplier_cnpj' => $p['supplier_cnpj'] ?? null,
                'supplier_name' => $p['supplier_name'] ?? null,
                'line_count' => $p['line_count'] ?? count($p['lines'] ?? []),
                'total_estimated_cents' => $p['total_estimated_cents'] ?? 0,
                'payload' => $p,
                'status' => PedidoLojaRecebido::STATUS_RECEBIDO,
                'recebido_em' => now(),
            ],
        );
    }
}
