<?php

use App\Enums\Perfil;
use App\Livewire\Almoxarife\SaldosEstoque;
use App\Livewire\Relatorios\PosicaoEstoque;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Cria um saldo controla_lote com os lotes informados.
 *
 * @param  array<int, ?string>  $validades  uma validade por lote (null = sem validade)
 */
function v3_saldoComLotes(Unidade $unidade, array $validades, float $porLote = 5.0): SaldoEstoque
{
    $catalogo = CatalogoItem::factory()->create(['controla_lote' => true]);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => count($validades) * $porLote,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => count($validades) * $porLote * 10.0,
        'item_catalogo_id' => $catalogo->id,
    ]);

    foreach ($validades as $i => $val) {
        LoteEstoque::factory()->create([
            'saldo_estoque_id' => $saldo->id,
            'numero_lote' => 'L-'.$i,
            'validade' => $val,
            'quantidade' => $porLote,
            'fundido_para_id' => null,
        ]);
    }

    return $saldo;
}

// ─── Helper validadesVivasPorSaldo ────────────────────────────────────────────

it('validades_vivas_retorna_min_e_max_por_saldo', function () {
    $unidade = Unidade::factory()->create();
    $saldo = v3_saldoComLotes($unidade, ['2027-05-01', '2027-12-31', '2027-08-15']);

    $mapa = LoteEstoque::validadesVivasPorSaldo([$saldo->id]);
    $v = $mapa->get($saldo->id);

    expect($v->min)->toBe('2027-05-01')
        ->and($v->max)->toBe('2027-12-31');
});

it('validades_vivas_ignora_lotes_sem_validade_e_tombstones', function () {
    $unidade = Unidade::factory()->create();
    $saldo = v3_saldoComLotes($unidade, ['2027-05-01', null]);

    // Tombstone com validade mais antiga não deve influenciar o min.
    LoteEstoque::factory()->create([
        'saldo_estoque_id' => $saldo->id,
        'numero_lote' => 'L-MORTO',
        'validade' => '2025-01-01',
        'quantidade' => 0,
        'fundido_para_id' => $saldo->lotesVivos()->first()->id,
        'fundido_em' => now(),
    ]);

    $v = LoteEstoque::validadesVivasPorSaldo([$saldo->id])->get($saldo->id);

    expect($v->min)->toBe('2027-05-01')   // null e tombstone ignorados
        ->and($v->max)->toBe('2027-05-01');
});

it('validades_vivas_inclui_lote_vencido_como_minimo', function () {
    $unidade = Unidade::factory()->create();
    // Hoje = 2026-06-19: 2025-01-01 está vencido e é o lote vivo de menor validade.
    $saldo = v3_saldoComLotes($unidade, ['2025-01-01', '2027-01-01']);

    $v = LoteEstoque::validadesVivasPorSaldo([$saldo->id])->get($saldo->id);

    expect($v->min)->toBe('2025-01-01');
});

it('saldo_sem_lote_nao_aparece_no_mapa_de_validades', function () {
    $unidade = Unidade::factory()->create();
    $saldoSemLote = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => 'Sem Lote',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Sem Lote'),
        'unidade_medida' => 'un',
        'quantidade' => 10.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 100.0,
    ]);

    $mapa = LoteEstoque::validadesVivasPorSaldo([$saldoSemLote->id]);

    expect($mapa->get($saldoSemLote->id))->toBeNull();
});

// ─── Render: PosicaoEstoque e SaldosEstoque mostram a validade ────────────────

it('posicao_estoque_exibe_a_validade_minima_formatada', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    v3_saldoComLotes($unidade, ['2027-05-01', '2027-12-31']);

    Livewire::actingAs($admin)
        ->test(PosicaoEstoque::class)
        ->assertSee('01/05/2027')    // min
        ->assertSee('31/12/2027');   // max
});

it('saldos_estoque_exibe_validade_para_item_com_lote', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    v3_saldoComLotes($unidade, ['2027-05-01']);

    Livewire::actingAs($almoxarife)
        ->test(SaldosEstoque::class)
        ->assertSee('01/05/2027');
});
