<?php

use App\Actions\TransferirEstoqueAction;
use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\TransferenciaEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function tr_saldo(Unidade $unidade, float $qtd, float $cmp, string $descricao = 'Item Transf', ?CatalogoItem $catalogo = null): SaldoEstoque
{
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo?->descricao ?? $descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo?->descricao ?? $descricao),
        'unidade_medida' => 'un',
        'quantidade' => $qtd,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $qtd * $cmp,
        'item_catalogo_id' => $catalogo?->id,
    ]);
}

function tr_almoxarife(Unidade $unidade): User
{
    $u = User::factory()->create();
    $u->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    return $u;
}

// ─── Mecânica de saldo ────────────────────────────────────────────────────────

it('transferencia_debita_origem_e_cria_saldo_no_destino', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $almox = tr_almoxarife($uOrigem);

    app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'Realocação', $almox);

    $origem->refresh();
    $destino = SaldoEstoque::where('unidade_id', $uDestino->id)->first();

    expect((float) $origem->quantidade)->toBe(6.0)
        ->and((float) $origem->custo_medio_ponderado)->toBe(50.0)   // CMP da origem não muda
        ->and((float) $origem->valor_total)->toEqualWithDelta(300.0, 0.01)
        ->and($destino)->not->toBeNull()
        ->and((float) $destino->quantidade)->toBe(4.0)
        ->and((float) $destino->custo_medio_ponderado)->toBe(50.0)  // herda o CMP da origem
        ->and((float) $destino->valor_total)->toEqualWithDelta(200.0, 0.01);
});

it('transferencia_recalcula_cmp_do_destino_existente_por_media_ponderada', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $destino = tr_saldo($uDestino, 10.0, 100.0); // mesma descrição → mesma identidade
    $almox = tr_almoxarife($uOrigem);

    app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 10.0, 'x', $almox);

    $destino->refresh();
    // (1000 + 10×50) / (10 + 10) = 1500/20 = 75
    expect((float) $destino->quantidade)->toBe(20.0)
        ->and((float) $destino->custo_medio_ponderado)->toBe(75.0)
        ->and((float) $destino->valor_total)->toEqualWithDelta(1500.0, 0.01)
        ->and((float) $origem->refresh()->quantidade)->toBe(0.0);
});

it('transferencia_conserva_o_valor_total_da_rede', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    tr_saldo($uDestino, 5.0, 80.0);
    $almox = tr_almoxarife($uOrigem);

    $valorAntes = (float) SaldoEstoque::sum('valor_total');

    app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $almox);

    expect((float) SaldoEstoque::sum('valor_total'))->toEqualWithDelta($valorAntes, 0.01);
});

// ─── Ledger ───────────────────────────────────────────────────────────────────

it('transferencia_gera_duas_movimentacoes_pareadas_e_vinculadas', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $almox = tr_almoxarife($uOrigem);

    $transf = app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $almox);

    $movs = MovimentacaoEstoque::where('transferencia_estoque_id', $transf->id)->get();
    $saida = $movs->firstWhere('tipo', TipoMovimentacao::TransferenciaSaida);
    $entrada = $movs->firstWhere('tipo', TipoMovimentacao::TransferenciaEntrada);

    expect($movs)->toHaveCount(2)
        ->and($saida->saldo_estoque_id)->toBe($origem->id)
        ->and($entrada->saldo_estoque_id)->toBe($transf->saldo_destino_id)
        ->and((float) $saida->custo_unitario)->toBe(50.0)            // CMP da origem
        ->and((float) $saida->valor_total)->toEqualWithDelta(200.0, 0.01)
        ->and((float) $transf->valor_total)->toEqualWithDelta(200.0, 0.01);
});

// ─── Guards ───────────────────────────────────────────────────────────────────

it('transferencia_saldo_insuficiente_reverte_tudo', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $almox = tr_almoxarife($uOrigem);

    expect(fn () => app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 15.0, 'x', $almox))
        ->toThrow(ValidationException::class);

    expect((float) $origem->refresh()->quantidade)->toBe(10.0)
        ->and(SaldoEstoque::where('unidade_id', $uDestino->id)->count())->toBe(0)
        ->and(TransferenciaEstoque::count())->toBe(0)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

it('transferencia_para_mesma_unidade_e_bloqueada', function () {
    $u = Unidade::factory()->create();
    $origem = tr_saldo($u, 10.0, 50.0);
    $almox = tr_almoxarife($u);

    expect(fn () => app(TransferirEstoqueAction::class)->execute($origem, $u, 4.0, 'x', $almox))
        ->toThrow(ValidationException::class);

    expect((float) $origem->refresh()->quantidade)->toBe(10.0);
});

it('transferencia_de_item_controla_lote_e_bloqueada', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create(['controla_lote' => true]);
    $origem = tr_saldo($uOrigem, 10.0, 50.0, catalogo: $catalogo);
    LoteEstoque::factory()->create(['saldo_estoque_id' => $origem->id, 'numero_lote' => 'L1', 'quantidade' => 10.0, 'fundido_para_id' => null]);
    $almox = tr_almoxarife($uOrigem);

    expect(fn () => app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $almox))
        ->toThrow(ValidationException::class);

    expect((float) $origem->refresh()->quantidade)->toBe(10.0)
        ->and(TransferenciaEstoque::count())->toBe(0);
});

it('transferencia_quantidade_invalida_e_bloqueada', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $almox = tr_almoxarife($uOrigem);

    expect(fn () => app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 0.0, 'x', $almox))
        ->toThrow(ValidationException::class);
});

// ─── Autorização ──────────────────────────────────────────────────────────────

it('transferencia_autorizada_para_almoxarife_da_origem', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $almox = tr_almoxarife($uOrigem);

    $transf = app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $almox);
    expect($transf)->toBeInstanceOf(TransferenciaEstoque::class);
});

it('transferencia_autorizada_para_admin', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $admin = User::factory()->admin()->create();

    $transf = app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $admin);
    expect($transf)->toBeInstanceOf(TransferenciaEstoque::class);
});

it('transferencia_barrada_para_almoxarife_de_outra_unidade', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $outro = tr_almoxarife($uDestino); // almoxarife do destino, não da origem

    expect(fn () => app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $outro))
        ->toThrow(ValidationException::class);

    expect((float) $origem->refresh()->quantidade)->toBe(10.0);
});

it('transferencia_barrada_para_usuario_sem_perfil', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $origem = tr_saldo($uOrigem, 10.0, 50.0);
    $semPerfil = User::factory()->create();

    expect(fn () => app(TransferirEstoqueAction::class)->execute($origem, $uDestino, 4.0, 'x', $semPerfil))
        ->toThrow(ValidationException::class);
});
