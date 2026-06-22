<?php

use App\Actions\GerarPagamentoDoPedidoAction;
use App\Enums\StatusPagamento;
use App\Enums\StatusPedidoCompra;
use App\Models\Fornecedor;
use App\Models\ItemPedidoCompra;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gera pagamento pendente do pedido (total = soma dos itens, vencimento +30d)', function () {
    $emissor = User::factory()->financeiro()->create();
    $fornecedor = Fornecedor::factory()->create();
    $pedido = PedidoCompra::factory()->create([
        'status' => StatusPedidoCompra::Emitido,
        'emitido_em' => now(),
        'fornecedor_id' => $fornecedor->id,
    ]);
    ItemPedidoCompra::factory()->create(['pedido_compra_id' => $pedido->id, 'valor_total' => 300]);
    ItemPedidoCompra::factory()->create(['pedido_compra_id' => $pedido->id, 'valor_total' => 200]);

    $pagamento = app(GerarPagamentoDoPedidoAction::class)->execute($pedido->fresh(), $emissor);

    expect((float) $pagamento->valor_total)->toBe(500.00)
        ->and($pagamento->status)->toBe(StatusPagamento::Pendente)
        ->and($pagamento->fornecedor_id)->toBe($fornecedor->id)
        ->and($pagamento->data_vencimento->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($pagamento->criado_por)->toBe($emissor->id);
});

it('é idempotente: não duplica pagamento para o mesmo pedido', function () {
    $emissor = User::factory()->financeiro()->create();
    $pedido = PedidoCompra::factory()->create(['status' => StatusPedidoCompra::Emitido, 'emitido_em' => now()]);
    ItemPedidoCompra::factory()->create(['pedido_compra_id' => $pedido->id, 'valor_total' => 100]);

    $p1 = app(GerarPagamentoDoPedidoAction::class)->execute($pedido->fresh(), $emissor);
    $p2 = app(GerarPagamentoDoPedidoAction::class)->execute($pedido->fresh(), $emissor);

    expect($p2->id)->toBe($p1->id)
        ->and(Pagamento::where('pedido_compra_id', $pedido->id)->count())->toBe(1);
});
