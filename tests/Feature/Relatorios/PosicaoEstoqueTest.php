<?php

use App\Livewire\Relatorios\PosicaoEstoque;
use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Autorização ───────────────────────────────────────────────────────────────

it('rota_posicao_estoque_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.posicao-estoque'))
        ->assertForbidden();
});

it('rota_posicao_estoque_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.posicao-estoque'))
        ->assertSuccessful();
});

// ─── Estado vazio ────────────────────────────────────────────────────────────

it('posicao_estoque_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.posicao-estoque'))
        ->assertSuccessful()
        ->assertSee('Nenhum saldo em estoque');
});

// ─── Listagem ──────────────────────────────────────────────────────────────────

it('posicao_estoque_exibe_saldo_vivo', function () {
    $unidade = Unidade::factory()->create();
    SaldoEstoque::factory()->create([
        'unidade_id' => $unidade->id,
        'descricao_item' => 'Cimento CP-II',
        'quantidade' => 42.0,
    ]);

    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.posicao-estoque'))
        ->assertSuccessful()
        ->assertSee('Cimento CP-II')
        ->assertSee('42,000');
});

// ─── Adversário: saldo fundido (tombstone) não conta ────────────────────────────

it('posicao_estoque_ignora_saldo_fundido', function () {
    $unidade = Unidade::factory()->create();

    $destino = SaldoEstoque::factory()->create([
        'unidade_id' => $unidade->id,
        'descricao_item' => 'Saldo Destino Vivo',
        'quantidade' => 100.0,
        'fundido_para_id' => null,
    ]);

    // Tombstone: saldo já fundido para o destino — NÃO deve ser contabilizado.
    SaldoEstoque::factory()->create([
        'unidade_id' => $unidade->id,
        'descricao_item' => 'Saldo Fundido Tombstone',
        'quantidade' => 999.0,
        'fundido_para_id' => $destino->id,
        'fundido_em' => now(),
    ]);

    $posicao = Livewire::actingAs(User::factory()->compradora()->create())
        ->test(PosicaoEstoque::class)
        ->viewData('posicao');

    expect($posicao)->toHaveCount(1);
    expect($posicao->first()->descricao_item)->toBe('Saldo Destino Vivo');
    expect($posicao->pluck('descricao_item'))->not->toContain('Saldo Fundido Tombstone');
});

// ─── Flag de alerta (item de catálogo abaixo do mínimo) ──────────────────────────

it('posicao_estoque_marca_em_alerta_quando_abaixo_do_minimo', function () {
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();

    SaldoEstoque::factory()->create([
        'unidade_id' => $unidade->id,
        'item_catalogo_id' => $item->id,
        'descricao_item' => 'Item Catalogado',
        'quantidade' => 5.0,
    ]);

    EstoqueMinimo::factory()->create([
        'unidade_id' => $unidade->id,
        'item_catalogo_id' => $item->id,
        'quantidade_minima' => 50.0,
    ]);

    $posicao = Livewire::actingAs(User::factory()->compradora()->create())
        ->test(PosicaoEstoque::class)
        ->viewData('posicao');

    expect($posicao->firstWhere('item_catalogo_id', $item->id)->em_alerta)->toBeTrue();
});
