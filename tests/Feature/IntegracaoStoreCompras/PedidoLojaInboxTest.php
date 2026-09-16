<?php

use App\Models\PedidoLojaRecebido;
use App\Subscribers\IngerirPedidoLoja;
use Helix\Foundation\Models\Platform\Event\DomainEvent;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Helix\Foundation\Services\Platform\Transport\WebhookSigner;
use Illuminate\Testing\TestResponse;

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
    // v0.2.0: tenant_id saiu do $fillable do DomainEvent — num evento montado à
    // mão (não persistido) a coluna entra por forceFill.
    $event = (new DomainEvent([
        'name' => IngerirPedidoLoja::EVENTO,
        'payload' => payloadPedidoLoja(),
    ]))->forceFill(['tenant_id' => TenantContext::id()]);

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
    // v0.2.0: a allowlist de tenants por remetente é FAIL-CLOSED — sem entrada
    // para `store`, o envelope assinado leva 403 (ver o caso logo abaixo).
    // v0.4.0: a allowlist de CONTRATOS também é fail-closed — explícita aqui para o
    // teste não depender do HELIX_INBOUND_ACCEPT do .env local.
    config([
        'foundation.inbound.secrets.store' => 'par-secreto',
        'foundation.inbound.tenants.store' => [TenantContext::id()],
        'foundation.inbound.accept' => [IngerirPedidoLoja::EVENTO],
    ]);

    $envelope = [
        'name' => IngerirPedidoLoja::EVENTO,
        'tenant_id' => TenantContext::id(),
        'version' => 1,
        'payload' => payloadPedidoLoja('PED-0002'),
    ];

    postAssinado($envelope, 'par-secreto')->assertStatus(202);

    // QUEUE_CONNECTION=sync → o ProcessDomainEvent já rodou o subscriber.
    expect(PedidoLojaRecebido::where('request_code', 'PED-0002')->count())->toBe(1);

    // reentrega assinada → mesma linha (idempotência do consumidor). O remetente
    // reassina com timestamp novo (senão o anti-replay da foundation devolve 409).
    test()->travel(2)->seconds();
    postAssinado($envelope, 'par-secreto')->assertStatus(202);
    expect(PedidoLojaRecebido::where('request_code', 'PED-0002')->count())->toBe(1);
});

it('rejeita (403) remetente sem allowlist de tenant e tenant fora dela', function () {
    config([
        'foundation.inbound.secrets.store' => 'par-secreto',
        'foundation.inbound.accept' => [IngerirPedidoLoja::EVENTO],
    ]);

    $envelope = [
        'name' => IngerirPedidoLoja::EVENTO,
        'tenant_id' => TenantContext::id(),
        'version' => 1,
        'payload' => payloadPedidoLoja('PED-0403'),
    ];

    // Sem HELIX_INBOUND_TENANTS_STORE configurado: fail-closed.
    postAssinado($envelope, 'par-secreto')->assertStatus(403);

    // Com a allowlist apontando para OUTRO tenant: também 403.
    config(['foundation.inbound.tenants.store' => ['01a00000-0000-7000-8000-000000000000']]);
    test()->travel(2)->seconds();
    postAssinado($envelope, 'par-secreto')->assertStatus(403);

    expect(PedidoLojaRecebido::where('request_code', 'PED-0403')->count())->toBe(0);
});

it('recusa (422) todo evento quando HELIX_INBOUND_ACCEPT está vazio e evento fora da allowlist', function () {
    // v0.4.0 — FAIL-CLOSED: até a v0.3.x `accept` vazio aceitava QUALQUER nome
    // assinado, e um segredo vazado injetava qualquer contrato no outbox do Compras.
    config([
        'foundation.inbound.secrets.store' => 'par-secreto',
        'foundation.inbound.tenants.store' => [TenantContext::id()],
        'foundation.inbound.accept' => [],
    ]);

    $envelope = [
        'name' => IngerirPedidoLoja::EVENTO,
        'tenant_id' => TenantContext::id(),
        'version' => 1,
        'payload' => payloadPedidoLoja('PED-0422'),
    ];

    postAssinado($envelope, 'par-secreto')->assertStatus(422);

    // Allowlist preenchida, mas com OUTRO contrato: o nosso continua recusado.
    config(['foundation.inbound.accept' => ['store.purchase_request.cancelled']]);
    test()->travel(2)->seconds();
    postAssinado($envelope, 'par-secreto')->assertStatus(422);

    // E um contrato não consumido pelo Compras, mesmo assinado, não entra.
    config(['foundation.inbound.accept' => [IngerirPedidoLoja::EVENTO]]);
    test()->travel(2)->seconds();
    postAssinado(['name' => 'store.qualquer.coisa'] + $envelope, 'par-secreto')->assertStatus(422);

    expect(PedidoLojaRecebido::where('request_code', 'PED-0422')->count())->toBe(0);
});

it('o .env.example declara a allowlist de contratos que o Compras consome', function () {
    $env = (string) file_get_contents(base_path('.env.example'));

    expect(preg_match('/^HELIX_INBOUND_ACCEPT=(.*)$/m', $env, $m))->toBe(1);

    $contratos = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    expect($contratos)->toBe([IngerirPedidoLoja::EVENTO]);
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
function postAssinado(array $envelope, string $secret): TestResponse
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
