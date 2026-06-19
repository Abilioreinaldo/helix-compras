<?php

use App\Actions\CalcularRateioMensalAction;
use App\Actions\DescontoRateioAction;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Mês/ano de referência (passado em relação a "hoje" = 2026-06-19). */
function rc_periodo(): array
{
    return ['mes' => 5, 'ano' => 2026];
}

/** Cria uma saída (consumo) de valor $valor numa unidade, datada em maio/2026. */
function rc_consumo(Unidade $unidade, User $registrador, float $valor): void
{
    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => "Consumo U{$unidade->id}",
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao("Consumo U{$unidade->id}"),
        'unidade_medida' => 'un',
        'quantidade' => 10.0,
        'custo_medio_ponderado' => $valor,
        'valor_total' => 10.0 * $valor,
    ]);

    $mov = MovimentacaoEstoque::create([
        'saldo_estoque_id' => $saldo->id,
        'tipo' => TipoMovimentacao::Saida,
        'quantidade' => 1,
        'custo_unitario' => $valor,
        'valor_total' => $valor,
        'motivo' => 'consumo teste',
        'registrado_por' => $registrador->id,
    ]);

    // created_at no mês de referência (via query builder, sem disparar eventos).
    MovimentacaoEstoque::where('id', $mov->id)->update(['created_at' => Carbon::create(2026, 5, 15, 12)]);
}

// ─── Cálculo de percentual ────────────────────────────────────────────────────

it('rateio_duas_unidades_5050', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $uA = Unidade::factory()->create();
    $uB = Unidade::factory()->create();
    rc_consumo($uA, $reg, 100.0);
    rc_consumo($uB, $reg, 100.0);

    ['mes' => $mes, 'ano' => $ano] = rc_periodo();
    $rateio = app(CalcularRateioMensalAction::class)->execute($mes, $ano, 1000.0, $admin);

    $linhas = $rateio->unidades->keyBy('unidade_id');

    expect((float) $linhas[$uA->id]->percentual_consumo)->toBe(0.5)
        ->and((float) $linhas[$uB->id]->percentual_consumo)->toBe(0.5)
        ->and((float) $linhas[$uA->id]->valor_rateado)->toBe(500.0)
        ->and((float) $linhas[$uB->id]->valor_rateado)->toBe(500.0)
        ->and((float) $rateio->unidades->sum('valor_rateado'))->toBe(1000.0);
});

it('rateio_tres_unidades_desiguais', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u1 = Unidade::factory()->create();
    $u2 = Unidade::factory()->create();
    $u3 = Unidade::factory()->create();
    rc_consumo($u1, $reg, 50.0);
    rc_consumo($u2, $reg, 30.0);
    rc_consumo($u3, $reg, 20.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linhas = $rateio->unidades->keyBy('unidade_id');

    expect((float) $linhas[$u1->id]->valor_rateado)->toBe(500.0)
        ->and((float) $linhas[$u2->id]->valor_rateado)->toBe(300.0)
        ->and((float) $linhas[$u3->id]->valor_rateado)->toBe(200.0)
        ->and((float) $rateio->unidades->sum('valor_rateado'))->toBe(1000.0);
});

it('rateio_uma_unidade_recebe_tudo', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);

    expect((float) $rateio->unidades->first()->percentual_consumo)->toBe(1.0)
        ->and((float) $rateio->unidades->first()->valor_rateado)->toBe(1000.0);
});

it('rateio_residuo_de_arredondamento_fecha_no_valor_central', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u1 = Unidade::factory()->create();
    $u2 = Unidade::factory()->create();
    $u3 = Unidade::factory()->create();
    // Consumo igual → 33,33% cada; 33,33 × 100 = 99,99 → resíduo 0,01.
    rc_consumo($u1, $reg, 10.0);
    rc_consumo($u2, $reg, 10.0);
    rc_consumo($u3, $reg, 10.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 100.0, $admin);

    // O resíduo fecha exatamente no valor da central.
    expect((float) $rateio->unidades->sum('valor_rateado'))->toBe(100.0);
});

it('rateio_unidade_sem_saida_fica_com_zero_por_cento', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $comConsumo = Unidade::factory()->create();
    $semConsumo = Unidade::factory()->create();
    rc_consumo($comConsumo, $reg, 100.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linhas = $rateio->unidades->keyBy('unidade_id');

    expect((float) $linhas[$semConsumo->id]->percentual_consumo)->toBe(0.0)
        ->and((float) $linhas[$semConsumo->id]->valor_rateado)->toBe(0.0)
        ->and((float) $linhas[$comConsumo->id]->valor_rateado)->toBe(1000.0)
        // Sem movimentação para a unidade de valor zero.
        ->and(MovimentacaoEstoque::where('tipo', TipoMovimentacao::RateioCentral->value)->count())->toBe(1);
});

// ─── Movimentação / ledger ────────────────────────────────────────────────────

it('rateio_cria_movimentacao_documental_correta', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->first();
    $mov = MovimentacaoEstoque::where('tipo', TipoMovimentacao::RateioCentral->value)->first();

    expect($mov->saldo_estoque_id)->toBeNull()                       // não toca estoque
        ->and($mov->rateio_unidade_id)->toBe($linha->id)
        ->and((float) $mov->valor_total)->toBe(1000.0)
        ->and($mov->registrado_por)->toBe($admin->id);
});

// ─── Idempotência ─────────────────────────────────────────────────────────────

it('rateio_idempotente_roda_duas_vezes_um_rateio', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);

    $r1 = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $r2 = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);

    expect(RateioCentral::count())->toBe(1)
        ->and($r2->id)->toBe($r1->id)
        ->and(RateioUnidade::count())->toBe(1);
});

// ─── Guards ───────────────────────────────────────────────────────────────────

it('rateio_so_admin', function () {
    $naoAdmin = User::factory()->create();
    Unidade::factory()->create();

    expect(fn () => app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $naoAdmin))
        ->toThrow(ValidationException::class);

    expect(RateioCentral::count())->toBe(0);
});

it('rateio_periodo_futuro_bloqueado', function () {
    $admin = User::factory()->admin()->create();
    Unidade::factory()->create();

    // Hoje = 2026-06-19 → julho/2026 é futuro.
    expect(fn () => app(CalcularRateioMensalAction::class)->execute(7, 2026, 1000.0, $admin))
        ->toThrow(ValidationException::class);
});

// ─── Reversão (DescontoRateio) ────────────────────────────────────────────────

it('desconto_cria_movimentacao_de_desconto', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->first();

    $mov = app(DescontoRateioAction::class)->execute($rateio, $linha, 'Erro no valor da central.', $admin);

    expect($mov->tipo)->toBe(TipoMovimentacao::DescontoRateio)
        ->and((float) $mov->valor_total)->toBe(1000.0)
        ->and($mov->motivo)->toBe('Erro no valor da central.')
        ->and($mov->rateio_unidade_id)->toBe($linha->id)
        ->and($linha->refresh()->foiRevertido())->toBeTrue();
});

it('desconto_so_admin', function () {
    $admin = User::factory()->admin()->create();
    $naoAdmin = User::factory()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);
    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->first();

    expect(fn () => app(DescontoRateioAction::class)->execute($rateio, $linha, 'Motivo', $naoAdmin))
        ->toThrow(ValidationException::class);

    expect(MovimentacaoEstoque::where('tipo', TipoMovimentacao::DescontoRateio->value)->count())->toBe(0);
});

it('desconto_nao_duplica_reversao', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);
    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->first();

    app(DescontoRateioAction::class)->execute($rateio, $linha, 'Primeira reversão.', $admin);

    expect(fn () => app(DescontoRateioAction::class)->execute($rateio, $linha, 'Segunda reversão.', $admin))
        ->toThrow(ValidationException::class);

    expect(MovimentacaoEstoque::where('tipo', TipoMovimentacao::DescontoRateio->value)->count())->toBe(1);
});

// ─── Regressão: rateio não toca saldo de estoque ──────────────────────────────

it('rateio_nao_altera_cmp_quantidade_ou_valor_total_do_saldo', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);

    $saldo = SaldoEstoque::first();
    $cmpAntes = (float) $saldo->custo_medio_ponderado;
    $qtdAntes = (float) $saldo->quantidade;
    $valorAntes = (float) $saldo->valor_total;

    app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);

    $saldo->refresh();
    expect((float) $saldo->custo_medio_ponderado)->toBe($cmpAntes)
        ->and((float) $saldo->quantidade)->toBe($qtdAntes)
        ->and((float) $saldo->valor_total)->toBe($valorAntes);
});

// ─── Command ──────────────────────────────────────────────────────────────────

it('command_rateio_executa_e_e_idempotente', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rc_consumo($u, $reg, 100.0);

    $this->artisan('rateio:executar-mensal', [
        '--valor-central' => '1000',
        '--mes' => 5,
        '--ano' => 2026,
        '--executado-por' => $admin->id,
    ])->assertExitCode(0);

    expect(RateioCentral::count())->toBe(1);

    // Segunda execução: idempotente, não duplica.
    $this->artisan('rateio:executar-mensal', [
        '--valor-central' => '1000',
        '--mes' => 5,
        '--ano' => 2026,
        '--executado-por' => $admin->id,
    ])->assertExitCode(0);

    expect(RateioCentral::count())->toBe(1);
});

it('command_rateio_recusa_nao_admin', function () {
    $naoAdmin = User::factory()->create();

    $this->artisan('rateio:executar-mensal', [
        '--valor-central' => '1000',
        '--mes' => 5,
        '--ano' => 2026,
        '--executado-por' => $naoAdmin->id,
    ])->assertExitCode(1);

    expect(RateioCentral::count())->toBe(0);
});
