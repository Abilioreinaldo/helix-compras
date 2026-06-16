<?php

use App\Enums\Perfil;
use App\Enums\StatusInventario;
use App\Livewire\Almoxarife\Inventario;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\SessaoInventario;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * @return array{unidade: Unidade, almoxarife: User, admin: User, saldo: SaldoEstoque}
 */
function inv_lw_setup(): array
{
    $unidade = Unidade::factory()->create();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $admin = User::factory()->admin()->create();

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito A',
        'descricao_item' => 'Material LW Test',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material LW Test'),
        'unidade_medida' => 'un',
        'quantidade' => 10.0,
        'custo_medio_ponderado' => 20.0,
        'valor_total' => 200.0,
    ]);

    return compact('unidade', 'almoxarife', 'admin', 'saldo');
}

// ─── Testes de abertura ──────────────────────────────────────────────────────

it('inventario_lw_almoxarife_pode_abrir_sessao', function () {
    $setup = inv_lw_setup();

    Livewire::actingAs($setup['almoxarife'])
        ->test(Inventario::class)
        ->call('abrirFormAbrir')
        ->set('depositoAbertura', 'Depósito A')
        ->call('abrirSessao');

    expect(SessaoInventario::count())->toBe(1)
        ->and(SessaoInventario::first()->status)->toBe(StatusInventario::EmAndamento);
});

it('inventario_lw_403_sem_perfil', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('almoxarife.inventario.index'))
        ->assertForbidden();
});

it('inventario_lw_erro_sessao_duplicada_exibe_erro', function () {
    $setup = inv_lw_setup();

    // Abre primeira sessão
    Livewire::actingAs($setup['almoxarife'])
        ->test(Inventario::class)
        ->call('abrirFormAbrir')
        ->set('depositoAbertura', 'Depósito A')
        ->call('abrirSessao');

    // Tenta abrir segunda sessão para o mesmo depósito
    $component = Livewire::actingAs($setup['almoxarife'])
        ->test(Inventario::class)
        ->call('abrirFormAbrir')
        ->set('depositoAbertura', 'Depósito A')
        ->call('abrirSessao');

    expect($component->get('erro'))->not->toBeEmpty();
    expect(SessaoInventario::count())->toBe(1);
});

// ─── Testes de aplicação ────────────────────────────────────────────────────

it('inventario_lw_aplicar_com_divergencia_ajusta_saldo', function () {
    $setup = inv_lw_setup();
    $saldo = $setup['saldo'];

    // Abrir sessão
    $component = Livewire::actingAs($setup['almoxarife'])
        ->test(Inventario::class)
        ->call('abrirFormAbrir')
        ->set('depositoAbertura', 'Depósito A')
        ->call('abrirSessao');

    $sessaoId = $component->get('sessaoAtivaId');
    $sessao = SessaoInventario::find($sessaoId);
    $item = $sessao->itens->first();

    // Preencher quantidade contada: 15 (divergência +5)
    $component->set("quantidadesContadas.{$item->id}", '15')
        ->call('abrirModalAplicar')
        ->set('justificativaAplicar', 'Contagem periódica de material.')
        ->call('aplicar');

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe(15.0)
        ->and(MovimentacaoEstoque::count())->toBe(1);
});

it('inventario_lw_cancelar_nao_gera_movimentacao', function () {
    $setup = inv_lw_setup();

    $component = Livewire::actingAs($setup['almoxarife'])
        ->test(Inventario::class)
        ->call('abrirFormAbrir')
        ->set('depositoAbertura', 'Depósito A')
        ->call('abrirSessao')
        ->call('cancelar');

    expect($component->get('sessaoAtivaId'))->toBeNull()
        ->and(MovimentacaoEstoque::count())->toBe(0)
        ->and(SessaoInventario::first()->status)->toBe(StatusInventario::Cancelado);
});
