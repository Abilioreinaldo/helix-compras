<?php

use App\Livewire\Admin\CatalogoItens\ListaCatalogoItens;
use App\Models\CatalogoItem;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('admin_liga_controle_de_lote_em_item_sem_saldo', function () {
    $admin = User::factory()->admin()->create();
    $item = CatalogoItem::factory()->create(['controla_lote' => false]);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('alternarControleLote', $item->id)
        ->assertHasNoErrors();

    expect($item->fresh()->controla_lote)->toBeTrue();
});

it('admin_desliga_controle_de_lote_sem_lotes_vivos', function () {
    $admin = User::factory()->admin()->create();
    $item = CatalogoItem::factory()->create(['controla_lote' => true]);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('alternarControleLote', $item->id)
        ->assertHasNoErrors();

    expect($item->fresh()->controla_lote)->toBeFalse();
});

it('ligar_controle_em_item_com_saldo_legado_mostra_erro_e_mantem_flag', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create(['controla_lote' => false]);

    // Saldo legado: quantidade > 0 e nenhum lote vinculado.
    SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $item->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($item->descricao),
        'unidade_medida' => 'un',
        'quantidade' => 5.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 50.0,
        'item_catalogo_id' => $item->id,
    ]);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('alternarControleLote', $item->id)
        ->assertHasErrors("controla_lote_{$item->id}");

    expect($item->fresh()->controla_lote)->toBeFalse();
});

it('nao_admin_recebe_403_na_tela_de_catalogo', function () {
    $semPerfil = User::factory()->create();

    Livewire::actingAs($semPerfil)
        ->test(ListaCatalogoItens::class)
        ->assertForbidden();
});
