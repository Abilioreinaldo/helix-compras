<?php

use App\Actions\AbrirSessaoInventarioAction;
use App\Actions\AplicarInventarioAction;
use App\Actions\CancelarSessaoInventarioAction;
use App\Enums\Perfil;
use App\Enums\StatusInventario;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * @return array{unidade: Unidade, almoxarife: User, admin: User, saldo1: SaldoEstoque, saldo2: SaldoEstoque}
 */
function inv_setup(): array
{
    $unidade = Unidade::factory()->create();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $admin = User::factory()->admin()->create();

    $saldo1 = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito A',
        'descricao_item' => 'Material Alpha',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material Alpha'),
        'unidade_medida' => 'un',
        'quantidade' => 20.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 200.0,
    ]);

    $saldo2 = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito A',
        'descricao_item' => 'Material Beta',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material Beta'),
        'unidade_medida' => 'cx',
        'quantidade' => 5.0,
        'custo_medio_ponderado' => 100.0,
        'valor_total' => 500.0,
    ]);

    return compact('unidade', 'almoxarife', 'admin', 'saldo1', 'saldo2');
}

// ─── AbrirSessaoInventarioAction ────────────────────────────────────────────

it('inventario_abrir_faz_snapshot_dos_saldos_do_deposito', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute(
        $setup['unidade'],
        'Depósito A',
        $setup['almoxarife'],
    );

    expect($sessao->status)->toBe(StatusInventario::EmAndamento)
        ->and($sessao->itens()->count())->toBe(2);

    $item1 = $sessao->itens()->where('saldo_estoque_id', $setup['saldo1']->id)->first();
    expect($item1)->not->toBeNull()
        ->and((float) $item1->quantidade_sistema)->toBe(20.0)
        ->and($item1->quantidade_contada)->toBeNull();
});

it('inventario_abrir_sem_deposito_inclui_todos_saldos_da_unidade', function () {
    $setup = inv_setup();

    // Adicionar saldo em outro depósito
    SaldoEstoque::create([
        'unidade_id' => $setup['unidade']->id,
        'deposito' => 'Depósito B',
        'descricao_item' => 'Material Gamma',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material Gamma'),
        'unidade_medida' => 'un',
        'quantidade' => 3.0,
        'custo_medio_ponderado' => 5.0,
        'valor_total' => 15.0,
    ]);

    // deposito null = unidade inteira
    $sessao = app(AbrirSessaoInventarioAction::class)->execute(
        $setup['unidade'],
        null,
        $setup['almoxarife'],
    );

    expect($sessao->itens()->count())->toBe(3);
});

it('inventario_abrir_exclui_tombstones_de_fusao', function () {
    $setup = inv_setup();

    // Criar um tombstone (fundido_para_id preenchido)
    $tombstone = SaldoEstoque::create([
        'unidade_id' => $setup['unidade']->id,
        'deposito' => 'Depósito A',
        'descricao_item' => 'Material Fundido',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material Fundido'),
        'unidade_medida' => 'un',
        'quantidade' => 0.0,
        'custo_medio_ponderado' => 0.0,
        'valor_total' => 0.0,
        'fundido_para_id' => $setup['saldo1']->id,
        'fundido_em' => now(),
    ]);

    $sessao = app(AbrirSessaoInventarioAction::class)->execute(
        $setup['unidade'],
        'Depósito A',
        $setup['almoxarife'],
    );

    // Tombstone não deve ser incluído no snapshot
    $idsNoSnap = $sessao->itens()->pluck('saldo_estoque_id')->toArray();
    expect(in_array($tombstone->id, $idsNoSnap))->toBeFalse()
        ->and($sessao->itens()->count())->toBe(2);
});

it('inventario_segunda_sessao_para_mesma_unidade_deposito_e_barrada', function () {
    $setup = inv_setup();

    app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);

    expect(fn () => app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

it('inventario_abrir_rejeita_usuario_sem_perfil', function () {
    $setup = inv_setup();
    $semPerfil = User::factory()->create();

    expect(fn () => app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $semPerfil))
        ->toThrow(HttpException::class);
});

// ─── AplicarInventarioAction ────────────────────────────────────────────────

it('inventario_aplicar_gera_ajustes_corretos_e_conclui_sessao', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);

    // saldo1: 20 contado 22 → divergência +2 (AjustePositivo)
    // saldo2: 5 contado 3  → divergência -2 (AjusteNegativo)
    $sessao->itens->each(function ($item) use ($setup) {
        if ($item->saldo_estoque_id === $setup['saldo1']->id) {
            $item->update(['quantidade_contada' => 22.0]);
        } else {
            $item->update(['quantidade_contada' => 3.0]);
        }
    });

    $sessao->load('itens');

    $sessaoConcluida = app(AplicarInventarioAction::class)->execute($sessao, 'Contagem anual de material.', $setup['almoxarife']);

    expect($sessaoConcluida->status)->toBe(StatusInventario::Concluido)
        ->and($sessaoConcluida->concluida_por)->toBe($setup['almoxarife']->id)
        ->and($sessaoConcluida->concluida_em)->not->toBeNull();

    // Verifica saldos
    $setup['saldo1']->refresh();
    $setup['saldo2']->refresh();
    expect((float) $setup['saldo1']->quantidade)->toBe(22.0)
        ->and((float) $setup['saldo2']->quantidade)->toBe(3.0);

    // Dois ajustes foram gerados
    expect(MovimentacaoEstoque::whereIn('tipo', [
        TipoMovimentacao::AjustePositivo->value,
        TipoMovimentacao::AjusteNegativo->value,
    ])->count())->toBe(2);
});

it('inventario_aplicar_sem_divergencia_nao_gera_movimentacao', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);

    // Contado = sistema (sem divergência)
    $sessao->itens->each(fn ($item) => $item->update(['quantidade_contada' => (float) $item->quantidade_sistema]));
    $sessao->load('itens');

    $sessaoConcluida = app(AplicarInventarioAction::class)->execute($sessao, 'Inventário rotineiro sem diferenças.', $setup['almoxarife']);

    expect($sessaoConcluida->status)->toBe(StatusInventario::Concluido)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

it('inventario_aplicar_barra_se_justificativa_vazia', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);
    $sessao->itens->each(fn ($item) => $item->update(['quantidade_contada' => 10.0]));
    $sessao->load('itens');

    expect(fn () => app(AplicarInventarioAction::class)->execute($sessao, '', $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

it('inventario_aplicar_barra_se_item_nao_contado', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);

    // Conta apenas um dos dois itens
    $sessao->itens->first()->update(['quantidade_contada' => 10.0]);
    $sessao->load('itens');

    expect(fn () => app(AplicarInventarioAction::class)->execute($sessao, 'Justificativa válida', $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

it('inventario_aplicar_nao_pode_ser_em_sessao_nao_em_andamento', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);
    $sessao->itens->each(fn ($item) => $item->update(['quantidade_contada' => (float) $item->quantidade_sistema]));
    $sessao->load('itens');

    app(CancelarSessaoInventarioAction::class)->execute($sessao, $setup['almoxarife']);
    $sessao->refresh();

    expect(fn () => app(AplicarInventarioAction::class)->execute($sessao, 'Justificativa.', $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

// ─── CancelarSessaoInventarioAction ─────────────────────────────────────────

it('inventario_cancelar_nao_gera_movimentacao', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);

    $sessaoCancelada = app(CancelarSessaoInventarioAction::class)->execute($sessao, $setup['almoxarife']);

    expect($sessaoCancelada->status)->toBe(StatusInventario::Cancelado)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

it('inventario_cancelar_sessao_nao_em_andamento_lanca_excecao', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);
    app(CancelarSessaoInventarioAction::class)->execute($sessao, $setup['almoxarife']);
    $sessao->refresh();

    expect(fn () => app(CancelarSessaoInventarioAction::class)->execute($sessao, $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

it('inventario_admin_pode_abrir_e_aplicar_em_qualquer_unidade', function () {
    $setup = inv_setup();
    $admin = $setup['admin'];

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $admin);
    $sessao->itens->each(fn ($item) => $item->update(['quantidade_contada' => (float) $item->quantidade_sistema]));
    $sessao->load('itens');

    $concluida = app(AplicarInventarioAction::class)->execute($sessao, 'Auditoria administrativa.', $admin);

    expect($concluida->status)->toBe(StatusInventario::Concluido);
});

it('inventario_admin_pode_aplicar_com_divergencia', function () {
    // Regressão do BUG-01: Admin precisa conseguir aplicar ajuste (AjusteEstoqueAction).
    $setup = inv_setup();
    $admin = $setup['admin'];

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $admin);

    // saldo1: 20 → contado 25 (+5); saldo2: sem divergência
    $sessao->itens->each(function ($item) use ($setup) {
        $item->update([
            'quantidade_contada' => $item->saldo_estoque_id === $setup['saldo1']->id
                ? 25.0
                : (float) $item->quantidade_sistema,
        ]);
    });
    $sessao->load('itens');

    $concluida = app(AplicarInventarioAction::class)->execute($sessao, 'Auditoria do Admin com divergência.', $admin);

    expect($concluida->status)->toBe(StatusInventario::Concluido)
        ->and((float) $setup['saldo1']->refresh()->quantidade)->toBe(25.0)
        ->and(MovimentacaoEstoque::where('tipo', TipoMovimentacao::AjustePositivo->value)->count())->toBe(1);
});

it('inventario_rollback_total_quando_ajuste_negativo_excede_saldo_real', function () {
    $setup = inv_setup();

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($setup['unidade'], 'Depósito A', $setup['almoxarife']);

    // Contagem: saldo1 +2 (22), saldo2 contado 0 (divergência -5 vs snapshot 5)
    $sessao->itens->each(function ($item) use ($setup) {
        $item->update([
            'quantidade_contada' => $item->saldo_estoque_id === $setup['saldo1']->id ? 22.0 : 0.0,
        ]);
    });
    $sessao->load('itens');

    // Saldo REAL de saldo2 cai para 1 APÓS o snapshot → ajuste negativo de 5 excede o saldo real
    $setup['saldo2']->update(['quantidade' => 1.0, 'valor_total' => 100.0]);

    expect(fn () => app(AplicarInventarioAction::class)->execute($sessao, 'Divergência grande.', $setup['almoxarife']))
        ->toThrow(ValidationException::class);

    // Rollback total: saldo1 inalterado, nenhuma movimentação, sessão segue em andamento
    expect((float) $setup['saldo1']->refresh()->quantidade)->toBe(20.0)
        ->and(MovimentacaoEstoque::count())->toBe(0)
        ->and($sessao->refresh()->status)->toBe(StatusInventario::EmAndamento);
});
