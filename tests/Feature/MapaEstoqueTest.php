<?php

use App\Livewire\Almoxarife\MapaEstoque;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function me_saldo(string $descricao, float $qtd, ?Unidade $unidade = null, bool $controlaLote = false): SaldoEstoque
{
    $unidade ??= Unidade::factory()->create();
    $item = CatalogoItem::factory()->create(['descricao' => $descricao, 'controla_lote' => $controlaLote]);

    return SaldoEstoque::factory()->create([
        'unidade_id' => $unidade->id,
        'item_catalogo_id' => $item->id,
        'descricao_item' => $descricao,
        'quantidade' => $qtd,
        'fundido_para_id' => null,
    ]);
}

it('lista os saldos de estoque', function () {
    me_saldo('Cimento CP-II 50kg', 120);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MapaEstoque::class)
        ->assertOk()
        ->assertSee('Cimento CP-II 50kg');
});

it('filtra por item (descrição)', function () {
    me_saldo('Cimento CP-II 50kg', 120);
    me_saldo('Areia média m³', 30);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MapaEstoque::class)
        ->set('filtroItem', 'Cimento')
        ->assertSee('Cimento CP-II 50kg')
        ->assertDontSee('Areia média m³');
});

it('classifica como Vencido quando há lote vivo com validade no passado', function () {
    $saldo = me_saldo('Argamassa AC-III', 50, controlaLote: true);
    LoteEstoque::factory()->create([
        'saldo_estoque_id' => $saldo->id,
        'numero_lote' => 'L-2026-001',
        'validade' => now()->subDays(5)->toDateString(),
        'quantidade' => 50,
        'fundido_para_id' => null,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MapaEstoque::class)
        ->assertSee('Vencido')
        ->assertSee('L-2026-001');
});

it('filtra por unidade', function () {
    $unidadeA = Unidade::factory()->create();
    $unidadeB = Unidade::factory()->create();
    me_saldo('Tijolo baiano', 800, $unidadeA);
    me_saldo('Brita 1 m³', 25, $unidadeB);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MapaEstoque::class)
        ->set('filtroUnidadeId', (string) $unidadeA->id)
        ->assertSee('Tijolo baiano')
        ->assertDontSee('Brita 1 m³');
});
