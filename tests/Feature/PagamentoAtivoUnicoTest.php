<?php

use App\Models\Pagamento;
use App\Models\PedidoCompra;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| A7 — "1 pagamento ATIVO por pedido" (índice único parcial driver-aware).
|--------------------------------------------------------------------------
|
| Migration `fix_unique_pagamento_por_pedido`:
|   - SQLite: CREATE UNIQUE INDEX ... (pedido_compra_id) WHERE deleted_at IS NULL
|   - MySQL:  coluna gerada STORED `pedido_ativo_key` + UNIQUE
|
| A invariante é a MESMA nos dois bancos — este teste é portável e roda em ambos.
| No SQLite é regressão; apontado ao MySQL real, é a evidência do checklist A7.
|
*/

uses(RefreshDatabase::class);

it('barra um segundo pagamento ATIVO para o mesmo pedido (A7)', function () {
    $pedido = PedidoCompra::factory()->create();
    Pagamento::factory()->create(['pedido_compra_id' => $pedido->id]);

    // Segundo pagamento ativo para o mesmo pedido viola o índice único parcial
    // (19 no SQLite, 1062 no MySQL) — ambos caem em QueryException.
    expect(fn () => Pagamento::factory()->create(['pedido_compra_id' => $pedido->id]))
        ->toThrow(QueryException::class);
});

it('libera novo pagamento ativo após soft-delete do anterior (A7)', function () {
    $pedido = PedidoCompra::factory()->create();
    $primeiro = Pagamento::factory()->create(['pedido_compra_id' => $pedido->id]);

    $primeiro->delete(); // soft delete → deleted_at setado → sai do índice de "ativos"

    $segundo = Pagamento::factory()->create(['pedido_compra_id' => $pedido->id]);

    expect($segundo->exists)->toBeTrue()
        ->and(Pagamento::where('pedido_compra_id', $pedido->id)->count())->toBe(1)
        ->and(Pagamento::withTrashed()->where('pedido_compra_id', $pedido->id)->count())->toBe(2);
});
