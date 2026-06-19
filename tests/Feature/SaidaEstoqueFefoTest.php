<?php

use App\Actions\SaidaEstoqueAction;
use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Monta um saldo controla_lote=true com os lotes informados, já satisfazendo a invariante
 * SUM(lotes vivos) == saldo.quantidade. Cada lote: ['numero' => ?, 'validade' => ?|null, 'qtd' => ?].
 *
 * @param  array<int, array{numero: string, validade: ?string, qtd: float}>  $lotes
 * @return array{unidade: Unidade, almoxarife: User, catalogo: CatalogoItem, saldo: SaldoEstoque}
 */
function sfefo_setup(array $lotes, float $cmp = 10.0): array
{
    $unidade = Unidade::factory()->create();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $catalogo = CatalogoItem::factory()->create([
        'descricao' => 'Insumo FEFO',
        'controla_lote' => true,
    ]);

    $total = array_sum(array_map(fn ($l) => $l['qtd'], $lotes));

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => $total,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $total * $cmp,
        'item_catalogo_id' => $catalogo->id,
    ]);

    foreach ($lotes as $l) {
        LoteEstoque::factory()->create([
            'saldo_estoque_id' => $saldo->id,
            'numero_lote' => $l['numero'],
            'validade' => $l['validade'],
            'quantidade' => $l['qtd'],
            'fundido_para_id' => null,
        ]);
    }

    return compact('unidade', 'almoxarife', 'catalogo', 'saldo');
}

/** Soma das quantidades dos lotes vivos do saldo. */
function sfefo_somaLotes(SaldoEstoque $saldo): float
{
    return (float) $saldo->lotesVivos()->sum('quantidade');
}

// ─── Ordenação FEFO ───────────────────────────────────────────────────────────

it('debita_o_lote_de_menor_validade_primeiro', function () {
    $s = sfefo_setup([
        ['numero' => 'L-JUN', 'validade' => '2027-06-01', 'qtd' => 5.0],
        ['numero' => 'L-JAN', 'validade' => '2027-01-01', 'qtd' => 5.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 3.0, 'Consumo', $s['almoxarife']);

    $jan = LoteEstoque::where('numero_lote', 'L-JAN')->first();
    $jun = LoteEstoque::where('numero_lote', 'L-JUN')->first();

    expect((float) $jan->quantidade)->toBe(2.0)   // menor validade consumido primeiro
        ->and((float) $jun->quantidade)->toBe(5.0)
        ->and((float) $s['saldo']->refresh()->quantidade)->toBe(7.0);
});

it('lote_sem_validade_e_consumido_por_ultimo', function () {
    $s = sfefo_setup([
        ['numero' => 'L-NULO', 'validade' => null, 'qtd' => 5.0],
        ['numero' => 'L-DATA', 'validade' => '2027-01-01', 'qtd' => 5.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 5.0, 'Consumo', $s['almoxarife']);

    expect((float) LoteEstoque::where('numero_lote', 'L-DATA')->value('quantidade'))->toBe(0.0)
        ->and((float) LoteEstoque::where('numero_lote', 'L-NULO')->value('quantidade'))->toBe(5.0);
});

// ─── Consumo multi-lote ───────────────────────────────────────────────────────

it('consome_across_multiplos_lotes', function () {
    $s = sfefo_setup([
        ['numero' => 'L-JAN', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-JUN', 'validade' => '2027-06-01', 'qtd' => 5.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 6.0, 'Consumo', $s['almoxarife']);

    expect((float) LoteEstoque::where('numero_lote', 'L-JAN')->value('quantidade'))->toBe(0.0)
        ->and((float) LoteEstoque::where('numero_lote', 'L-JUN')->value('quantidade'))->toBe(3.0)
        ->and((float) $s['saldo']->refresh()->quantidade)->toBe(3.0)
        ->and(sfefo_somaLotes($s['saldo']))->toBe(3.0);
});

it('multi_lote_tres_lotes_debita_em_ordem_e_soma_ao_saldo', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 2.0],
        ['numero' => 'L-B', 'validade' => '2027-02-01', 'qtd' => 3.0],
        ['numero' => 'L-C', 'validade' => '2027-03-01', 'qtd' => 4.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 6.0, 'Consumo', $s['almoxarife']);

    // 2 (A) + 3 (B) + 1 (C) = 6
    expect((float) LoteEstoque::where('numero_lote', 'L-A')->value('quantidade'))->toBe(0.0)
        ->and((float) LoteEstoque::where('numero_lote', 'L-B')->value('quantidade'))->toBe(0.0)
        ->and((float) LoteEstoque::where('numero_lote', 'L-C')->value('quantidade'))->toBe(3.0)
        ->and((float) $s['saldo']->refresh()->quantidade)->toBe(3.0)
        ->and(sfefo_somaLotes($s['saldo']))->toBe(3.0);
});

it('uma_movimentacao_por_lote_consumido_com_lote_estoque_id', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 5.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 6.0, 'Consumo', $s['almoxarife']);

    $saidas = MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->get();

    expect($saidas)->toHaveCount(2)                              // 1 movimentação por lote
        ->and($saidas->whereNull('lote_estoque_id'))->toHaveCount(0)   // todas com lote
        ->and((float) $saidas->sum('quantidade'))->toBe(6.0);
});

// ─── Custo: CMP do saldo, não PEPS ────────────────────────────────────────────

it('custo_unitario_e_o_cmp_do_saldo_e_o_cmp_nao_muda', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 5.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 5.0],
    ], cmp: 7.5);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 3.0, 'Consumo', $s['almoxarife']);

    $mov = MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->first();

    expect((float) $mov->custo_unitario)->toBe(7.5)             // CMP do saldo, não valor por lote
        ->and((float) $mov->valor_total)->toEqualWithDelta(22.5, 0.01)
        ->and((float) $s['saldo']->refresh()->custo_medio_ponderado)->toBe(7.5)   // CMP inalterado
        ->and((float) $s['saldo']->valor_total)->toEqualWithDelta(52.5, 0.01);    // 7 restantes × 7.5
});

it('lote_sem_validade_consumido_nao_marca_alerta_no_motivo', function () {
    $s = sfefo_setup([
        ['numero' => 'L-NULO', 'validade' => null, 'qtd' => 5.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 3.0, 'Consumo limpo', $s['almoxarife']);

    $mov = MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->first();

    expect($mov->motivo)->toBe('Consumo limpo')               // sem validade → sem [ALERTA]
        ->and($mov->motivo)->not->toContain('ALERTA');
});

it('invariante_quebrada_saldo_sem_lotes_aborta_e_reverte', function () {
    // Saldo controla_lote com quantidade > 0 mas SEM nenhum lote: SUM(lotes)=0 != saldo.
    $unidade = Unidade::factory()->create();
    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);
    $catalogo = CatalogoItem::factory()->create(['controla_lote' => true]);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => 5.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 50.0,
        'item_catalogo_id' => $catalogo->id,
    ]);

    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 3.0, 'Tentativa', $almoxarife))
        ->toThrow(RuntimeException::class);

    // Reverteu: saldo intacto, nenhuma movimentação.
    expect((float) $saldo->refresh()->quantidade)->toBe(5.0)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

// ─── Vencido: consome com alerta, nunca lança ─────────────────────────────────

it('lote_vencido_e_consumido_com_alerta_no_ledger_sem_lancar', function () {
    // Hoje é 2026-06-18: 2025-06-01 está vencido, 2027-06-01 não.
    $s = sfefo_setup([
        ['numero' => 'L-VENC', 'validade' => '2025-06-01', 'qtd' => 5.0],
        ['numero' => 'L-OK', 'validade' => '2027-06-01', 'qtd' => 5.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 3.0, 'Consumo', $s['almoxarife']);

    $loteVencido = LoteEstoque::where('numero_lote', 'L-VENC')->first();
    $mov = MovimentacaoEstoque::where('lote_estoque_id', $loteVencido->id)
        ->where('tipo', TipoMovimentacao::Saida)->first();

    expect((float) $loteVencido->quantidade)->toBe(2.0)         // vencido consumido primeiro (FEFO)
        ->and($mov->motivo)->toContain('ALERTA')
        ->and($mov->motivo)->toContain('vencido')
        ->and((float) $s['saldo']->refresh()->quantidade)->toBe(7.0);
});

it('lote_vencido_unico_nao_bloqueia_saida_total', function () {
    $s = sfefo_setup([
        ['numero' => 'L-VENC', 'validade' => '2024-01-01', 'qtd' => 4.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 4.0, 'Consumo', $s['almoxarife']);

    expect((float) $s['saldo']->refresh()->quantidade)->toBe(0.0)
        ->and((float) LoteEstoque::where('numero_lote', 'L-VENC')->value('quantidade'))->toBe(0.0);
});

// ─── Saldo insuficiente: reverte tudo ─────────────────────────────────────────

it('saldo_insuficiente_reverte_tudo_nenhum_lote_debitado', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 6.0],
    ]);

    expect(fn () => app(SaidaEstoqueAction::class)->execute($s['saldo'], 15.0, 'Excesso', $s['almoxarife']))
        ->toThrow(ValidationException::class);

    // Nenhum lote tocado, saldo intacto, nenhuma movimentação criada.
    expect((float) LoteEstoque::where('numero_lote', 'L-A')->value('quantidade'))->toBe(4.0)
        ->and((float) LoteEstoque::where('numero_lote', 'L-B')->value('quantidade'))->toBe(6.0)
        ->and((float) $s['saldo']->refresh()->quantidade)->toBe(10.0)
        ->and(MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->count())->toBe(0);
});

// ─── Invariante SUM(lotes) == saldo antes e depois ────────────────────────────

it('invariante_sum_lotes_igual_saldo_antes_e_depois', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 3.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 7.0],
    ]);

    // Antes
    expect(sfefo_somaLotes($s['saldo']))->toBe((float) $s['saldo']->quantidade);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 5.0, 'Consumo', $s['almoxarife']);

    // Depois
    $s['saldo']->refresh();
    expect(sfefo_somaLotes($s['saldo']))->toBe((float) $s['saldo']->quantidade)
        ->and((float) $s['saldo']->quantidade)->toBe(5.0);
});

it('lote_totalmente_consumido_permanece_vivo_com_quantidade_zero', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 6.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 4.0, 'Consumo', $s['almoxarife']);

    $loteA = LoteEstoque::where('numero_lote', 'L-A')->first();

    expect((float) $loteA->quantidade)->toBe(0.0)
        ->and($loteA->fundido_para_id)->toBeNull()              // continua vivo, não vira tombstone
        ->and(sfefo_somaLotes($s['saldo']))->toBe((float) $s['saldo']->refresh()->quantidade);
});

it('saida_exata_zera_saldo_e_todos_os_lotes', function () {
    $s = sfefo_setup([
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 6.0],
    ]);

    app(SaidaEstoqueAction::class)->execute($s['saldo'], 10.0, 'Consumo total', $s['almoxarife']);

    expect((float) $s['saldo']->refresh()->quantidade)->toBe(0.0)
        ->and(sfefo_somaLotes($s['saldo']))->toBe(0.0)
        ->and(MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->count())->toBe(2);
});
