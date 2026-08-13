<?php

use App\Models\PedidoLojaRecebido;
use App\Subscribers\IngerirPedidoLoja;
use Helix\Foundation\Models\Platform\Event\DomainEvent;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Helix\Foundation\Services\Platform\Transport\WebhookSigner;

/**
 * ADR-015 (Fatia 3) — Compras consome `store.purchase_request.created` no inbox
 * `pedidos_loja_recebidos`, idempotente por tenant + request_code.
 */
function payloadPedidoLoja(string $code = 'PED-0001'): array
{
    return [
        'version' => 1,
        'request_code' => $code,
        'store_code' => 'LOJ-0001',
        'supplier_cnpj' => '61585865000151',
        'supplier_name' => 'Distribuidora ABC LTDA',
        'line_count' => 1,
        'total_estimated_cents' => 9600,
        'lines' => [['product_code' => 'PRD-0001', 'line_total_cents' => 9600]],
    ];
}

it('o subscriber grava o pedido no inbox e é idempotente', function () {
    $event = new DomainEvent([
        'name' => IngerirPedidoLoja::EVENTO,
        'tenant_id' => TenantContext::id(),
        'payload' => payloadPedidoLoja(),
    ]);

    app(IngerirPedidoLoja::class)->handle($event);
    app(IngerirPedidoLoja::class)->handle($event); // entrega repetida (at-least-once)

    expect(PedidoLojaRecebido::where('request_code', 'PED-0001')->count())->toBe(1);

    $pedido = PedidoLojaRecebido::where('request_code', 'PED-0001')->first();
    expect($pedido->supplier_cnpj)->toBe('61585865000151')
        ->and($pedido->total_estimated_cents)->toBe(9600)
        ->and($pedido->status)->toBe(PedidoLojaRecebido::STATUS_RECEBIDO)
        ->and($pedido->payload['store_code'])->toBe('LOJ-0001');
});

it('recebe o evento assinado no /api/inbound/events e materializa o inbox', function () {
    config(['foundation.inbound.secrets.store' => 'par-secreto']);

    $envelope = [
        'name' => IngerirPedidoLoja::EVENTO,
        'tenant_id' => TenantContext::id(),
        'version' => 1,
        'payload' => payloadPedidoLoja('PED-0002'),
    ];

    postAssinado($envelope, 'par-secreto')->assertStatus(202);

    // QUEUE_CONNECTION=sync → o ProcessDomainEvent já rodou o subscriber.
    expect(PedidoLojaRecebido::where('request_code', 'PED-0002')->count())->toBe(1);

    // reentrega assinada → mesma linha (idempotência do consumidor)
    postAssinado($envelope, 'par-secreto')->assertStatus(202);
    expect(PedidoLojaRecebido::where('request_code', 'PED-0002')->count())->toBe(1);
});

it('rejeita (401) envelope com assinatura inválida', function () {
    config(['foundation.inbound.secrets.store' => 'par-secreto']);

    $body = json_encode(['name' => IngerirPedidoLoja::EVENTO, 'payload' => payloadPedidoLoja('PED-X')]);

    test()->call('POST', '/api/inbound/events', [], [], [], [
        'HTTP_X_HELIX_SENDER' => 'store',
        'HTTP_X_HELIX_TIMESTAMP' => (string) now()->getTimestamp(),
        'HTTP_X_HELIX_SIGNATURE' => 'assinatura-falsa',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body)->assertStatus(401);

    expect(PedidoLojaRecebido::count())->toBe(0);
});

/** POST assinado com corpo bruto controlado (para o HMAC bater). */
function postAssinado(array $envelope, string $secret): \Illuminate\Testing\TestResponse
{
    $body = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ts = now()->getTimestamp();
    $sig = (new WebhookSigner)->sign($body, $secret, $ts);

    return test()->call('POST', '/api/inbound/events', [], [], [], [
        'HTTP_X_HELIX_SENDER' => 'store',
        'HTTP_X_HELIX_TIMESTAMP' => (string) $ts,
        'HTTP_X_HELIX_SIGNATURE' => $sig,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}
