<?php

use App\Models\CatalogoItem;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| 2ª auditoria adversarial — chaves naturais "por tenant por transitividade".
|--------------------------------------------------------------------------
|
| Três uniques continuavam GLOBAIS e só não colidiam entre empresas porque a FK
| da esquerda pertence a uma delas — garantia que o BANCO não faz. Uma linha com
| FK cruzada (bug, import, restore parcial) NEGAVA PERMANENTEMENTE a operação
| legítima ao outro tenant, que nem conseguia ver o que o bloqueava.
|
| Migration: 2026_09_17_000004_uniques_por_tenant_pagamentos_cotacoes_saldos.
|
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantA = Tenant::create(['slug' => 'alfa-uq', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-uq', 'name' => 'Bravo', 'status' => 'active']);

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        $tenant->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    }

    TenantContext::forget();
});

/** Nomes das colunas do índice único informado (ou null se ele não existe). */
function uq_colunas(string $tabela, string $indice): ?array
{
    foreach (Schema::getIndexes($tabela) as $idx) {
        if (($idx['name'] ?? null) === $indice) {
            return array_map('strtolower', $idx['columns'] ?? []);
        }
    }

    return null;
}

it('as três chaves naturais carregam tenant_id', function () {
    // Estrutural: é a garantia que substitui a transitividade da FK. Para `cotacoes`
    // não há teste de comportamento possível — `deleted_at` NULL é distinto em índice
    // único nos dois bancos, então aquele unique nunca dispara para linhas ativas
    // (observação registrada no docblock da migration, para o backlog de produto).
    expect(uq_colunas('pagamentos', 'pagamentos_tenant_pedido_ativo_uq'))->toContain('tenant_id')
        ->and(uq_colunas('cotacoes', 'cotacoes_tenant_req_fornecedor_uq'))->toContain('tenant_id')
        ->and(uq_colunas('saldos_estoque', 'saldos_estoque_tenant_catalogo_uq'))->toContain('tenant_id');

    // E os globais antigos saíram de cena.
    expect(uq_colunas('pagamentos', 'pagamentos_pedido_ativo_unique'))->toBeNull()
        ->and(uq_colunas('saldos_estoque', 'saldos_estoque_catalogo_unique'))->toBeNull()
        ->and(uq_colunas('cotacoes', 'cotacoes_requisicao_id_fornecedor_id_deleted_at_unique'))->toBeNull();
});

it('um pagamento de B pendurado no pedido de A não impede A de pagar o próprio pedido', function () {
    $pedidoA = TenantContext::runFor($this->tenantA->id, fn () => PedidoCompra::factory()->create());

    // A linha envenenada: pagamento DE B apontando (FK cruzada) para o pedido DE A.
    TenantContext::runFor($this->tenantB->id, function () use ($pedidoA) {
        $pagamentoB = Pagamento::factory()->create();
        DB::table('pagamentos')->where('id', $pagamentoB->id)->update(['pedido_compra_id' => $pedidoA->id]);
    });

    // Com o unique GLOBAL, isto estourava QueryException e A ficava sem poder pagar —
    // para sempre, sem enxergar a linha que o bloqueia.
    $pagamentoA = TenantContext::runFor(
        $this->tenantA->id,
        fn () => Pagamento::factory()->create(['pedido_compra_id' => $pedidoA->id])
    );

    expect($pagamentoA->exists)->toBeTrue()
        ->and((string) $pagamentoA->tenant_id)->toBe((string) $this->tenantA->id);
});

it('um saldo de B na unidade de A não impede A de criar o próprio saldo', function () {
    [$unidadeA, $itemA] = TenantContext::runFor($this->tenantA->id, fn () => [
        Unidade::factory()->create(),
        CatalogoItem::factory()->create(),
    ]);

    TenantContext::runFor($this->tenantB->id, function () use ($unidadeA, $itemA) {
        $saldoB = SaldoEstoque::factory()->create(['item_catalogo_id' => $itemA->id]);
        DB::table('saldos_estoque')->where('id', $saldoB->id)->update([
            'unidade_id' => $unidadeA->id,
            'deposito' => 'Almoxarifado Central',
        ]);
    });

    $saldoA = TenantContext::runFor($this->tenantA->id, fn () => SaldoEstoque::factory()->create([
        'unidade_id' => $unidadeA->id,
        'deposito' => 'Almoxarifado Central',
        'item_catalogo_id' => $itemA->id,
    ]));

    expect($saldoA->exists)->toBeTrue();
});

it('a chave por tenant ainda barra a duplicata DENTRO do mesmo tenant', function () {
    // A correção não pode virar um afrouxamento: dentro do tenant, a invariante segue.
    TenantContext::runFor($this->tenantA->id, function () {
        $pedido = PedidoCompra::factory()->create();
        Pagamento::factory()->create(['pedido_compra_id' => $pedido->id]);

        expect(fn () => Pagamento::factory()->create(['pedido_compra_id' => $pedido->id]))
            ->toThrow(QueryException::class);

        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        SaldoEstoque::factory()->create([
            'unidade_id' => $unidade->id, 'deposito' => 'Dep Único', 'item_catalogo_id' => $item->id,
        ]);

        expect(fn () => SaldoEstoque::factory()->create([
            'unidade_id' => $unidade->id, 'deposito' => 'Dep Único', 'item_catalogo_id' => $item->id,
        ]))->toThrow(QueryException::class);
    });
});

it('a migration dos uniques por tenant é reversível e idempotente', function () {
    $migration = require database_path('migrations/2026_09_17_000004_uniques_por_tenant_pagamentos_cotacoes_saldos.php');

    $migration->down();

    expect(uq_colunas('pagamentos', 'pagamentos_tenant_pedido_ativo_uq'))->toBeNull()
        ->and(uq_colunas('pagamentos', 'pagamentos_pedido_ativo_unique'))->not->toBeNull()
        ->and(uq_colunas('saldos_estoque', 'saldos_estoque_catalogo_unique'))->not->toBeNull()
        ->and(uq_colunas('cotacoes', 'cotacoes_requisicao_id_fornecedor_id_deleted_at_unique'))->not->toBeNull();

    $migration->up();
    $migration->up(); // aditiva: rodar de novo sobre a base já migrada não quebra

    expect(uq_colunas('pagamentos', 'pagamentos_tenant_pedido_ativo_uq'))->toContain('tenant_id')
        ->and(uq_colunas('cotacoes', 'cotacoes_tenant_req_fornecedor_uq'))->toContain('tenant_id')
        ->and(uq_colunas('saldos_estoque', 'saldos_estoque_tenant_catalogo_uq'))->toContain('tenant_id');
});

it('a migration recusa subir sobre linha órfã (tenant_id NULL), em vez de criar índice que mente', function () {
    $pedido = TenantContext::runFor($this->tenantA->id, fn () => PedidoCompra::factory()->create());
    $pagamento = TenantContext::runFor(
        $this->tenantA->id,
        fn () => Pagamento::factory()->create(['pedido_compra_id' => $pedido->id])
    );

    DB::table('pagamentos')->where('id', $pagamento->id)->update(['tenant_id' => null]);

    $migration = require database_path('migrations/2026_09_17_000004_uniques_por_tenant_pagamentos_cotacoes_saldos.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
});
