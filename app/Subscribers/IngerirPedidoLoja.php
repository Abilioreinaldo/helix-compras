<?php

declare(strict_types=1);

namespace App\Subscribers;

use App\Models\PedidoLojaRecebido;
use Helix\Foundation\Models\Platform\Event\DomainEvent;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Event\Contracts\Subscriber;
use Helix\Foundation\Services\Platform\Identity\EntitlementService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\Log;

/**
 * Consome `store.purchase_request.created` (ADR-015): grava o pedido da loja no
 * inbox `pedidos_loja_recebidos`, idempotente por tenant_id + request_code.
 *
 * O evento chega ao outbox do Compras pelo receptor inbound assinado da
 * foundation; este subscriber roda no ProcessDomainEvent, sob o TenantContext
 * do evento (o BelongsToTenant carimba/escopa o tenant automaticamente).
 *
 * Defesa em profundidade (a foundation v0.1.9 já exige tenant no receptor):
 * evento sem tenant, de tenant inexistente/inativo ou de tenant que NÃO assina
 * a feature `compras` é descartado — nunca roda sem escopo nem escreve em
 * tenant que não é cliente do Compras.
 *
 * NÃO cria Requisicao: a promoção é manual (mapear loja→unidade, CNPJ→fornecedor,
 * itens→catálogo). Entrega é at-least-once — a idempotência é aqui.
 */
class IngerirPedidoLoja implements Subscriber
{
    public const EVENTO = 'store.purchase_request.created';

    public function __construct(private EntitlementService $entitlements) {}

    public function handle(DomainEvent $event): void
    {
        if (! $this->tenantHabilitado($event)) {
            return;
        }

        // Contexto explícito do tenant DO EVENTO (modo estrito): não depende de quem
        // chamou o subscriber ter estabelecido o TenantContext.
        TenantContext::runFor((string) $event->tenant_id, fn () => $this->ingerir($event));
    }

    private function ingerir(DomainEvent $event): void
    {
        $p = $event->payload;

        if (empty($p['request_code'])) {
            return; // sem a chave natural não há o que ingerir (contrato inválido)
        }

        // Idempotência por tenant + request_code (o unique da tabela é a rede final).
        $jaRecebido = PedidoLojaRecebido::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('request_code', $p['request_code'])
            ->exists();

        if ($jaRecebido) {
            return;
        }

        $pedido = new PedidoLojaRecebido([
            'request_code' => $p['request_code'],
            'store_code' => $p['store_code'] ?? null,
            'supplier_cnpj' => $p['supplier_cnpj'] ?? null,
            'supplier_name' => $p['supplier_name'] ?? null,
            'line_count' => $p['line_count'] ?? count($p['lines'] ?? []),
            'total_estimated_cents' => $p['total_estimated_cents'] ?? 0,
            'payload' => $p,
            'status' => PedidoLojaRecebido::STATUS_RECEBIDO,
            'recebido_em' => now(),
        ]);

        // tenant_id é system-controlled (fora do fillable): carimbo explícito com o tenant DO EVENTO.
        $pedido->forceFill(['tenant_id' => $event->tenant_id])->save();
    }

    /** Tenant presente, existente e com a feature `compras` ligada. */
    private function tenantHabilitado(DomainEvent $event): bool
    {
        $tenantId = $event->tenant_id;

        if ($tenantId === null || $tenantId === '') {
            Log::warning('IngerirPedidoLoja: evento sem tenant_id descartado.', ['event_id' => $event->id]);

            return false;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null || ! ($this->entitlements->list($tenant)['compras'] ?? false)) {
            Log::warning('IngerirPedidoLoja: tenant sem a feature compras — evento descartado.', [
                'event_id' => $event->id,
                'tenant_id' => $tenantId,
            ]);

            return false;
        }

        return true;
    }
}
