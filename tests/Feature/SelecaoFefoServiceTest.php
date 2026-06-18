<?php

use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use App\Services\SelecaoFefoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Cria um lote vivo no saldo com validade e numero_lote explícitos. */
function fefo_lote(SaldoEstoque $saldo, ?string $validade, string $numeroLote, float $quantidade = 10.0): LoteEstoque
{
    return LoteEstoque::factory()->create([
        'saldo_estoque_id' => $saldo->id,
        'numero_lote' => $numeroLote,
        'validade' => $validade,
        'quantidade' => $quantidade,
        'fundido_para_id' => null,
    ]);
}

it('ordena_por_menor_validade_primeiro_e_sem_validade_por_ultimo', function () {
    $saldo = SaldoEstoque::factory()->create();

    fefo_lote($saldo, '2027-06-01', 'L-JUN');
    fefo_lote($saldo, null, 'L-NULO');
    fefo_lote($saldo, '2027-01-01', 'L-JAN');
    fefo_lote($saldo, '2027-03-01', 'L-MAR');

    $ordem = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldo)
        ->pluck('numero_lote')->all();

    expect($ordem)->toBe(['L-JAN', 'L-MAR', 'L-JUN', 'L-NULO']);
});

it('desempata_por_id_quando_a_validade_e_igual', function () {
    $saldo = SaldoEstoque::factory()->create();

    $primeiro = fefo_lote($saldo, '2027-05-01', 'L-A');
    $segundo = fefo_lote($saldo, '2027-05-01', 'L-B');

    $ids = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldo)
        ->pluck('id')->all();

    expect($ids)->toBe([$primeiro->id, $segundo->id]);
});

it('desempata_por_id_entre_lotes_sem_validade', function () {
    $saldo = SaldoEstoque::factory()->create();

    $primeiro = fefo_lote($saldo, null, 'L-N1');
    $segundo = fefo_lote($saldo, null, 'L-N2');

    $ids = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldo)
        ->pluck('id')->all();

    expect($ids)->toBe([$primeiro->id, $segundo->id]);
});

it('saldo_sem_lotes_retorna_collection_vazia', function () {
    $saldo = SaldoEstoque::factory()->create();

    $lotes = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldo);

    expect($lotes)->toBeEmpty()
        ->and($lotes)->toHaveCount(0);
});

it('exclui_tombstones_mesmo_com_validade_mais_proxima', function () {
    $saldo = SaldoEstoque::factory()->create();

    $vivo = fefo_lote($saldo, '2027-10-01', 'L-VIVO');
    // Tombstone com validade ANTERIOR — se entrasse no FEFO, viria primeiro. Deve ficar fora.
    $tombstone = fefo_lote($saldo, '2026-01-01', 'L-MORTO');
    $tombstone->update(['fundido_para_id' => $vivo->id, 'fundido_em' => now()]);

    $ordem = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldo);

    expect($ordem)->toHaveCount(1)
        ->and($ordem->pluck('numero_lote')->all())->toBe(['L-VIVO']);
});

it('retorna_apenas_lotes_do_saldo_informado', function () {
    $saldoA = SaldoEstoque::factory()->create();
    $saldoB = SaldoEstoque::factory()->create();

    fefo_lote($saldoA, '2027-01-01', 'A-1');
    fefo_lote($saldoB, '2026-01-01', 'B-1'); // validade menor, mas em OUTRO saldo

    $ordem = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldoA)
        ->pluck('numero_lote')->all();

    expect($ordem)->toBe(['A-1']);
});

it('multiplos_lotes_misturando_datas_e_nulos_mantem_a_ordem_fefo', function () {
    $saldo = SaldoEstoque::factory()->create();

    fefo_lote($saldo, null, 'L-N');
    fefo_lote($saldo, '2028-12-31', 'L-LONGE');
    fefo_lote($saldo, '2026-02-15', 'L-PERTO');
    fefo_lote($saldo, '2027-07-20', 'L-MEIO');

    $ordem = app(SelecaoFefoService::class)->lotesPorOrdemFefo($saldo)
        ->pluck('numero_lote')->all();

    expect($ordem)->toBe(['L-PERTO', 'L-MEIO', 'L-LONGE', 'L-N']);
});
