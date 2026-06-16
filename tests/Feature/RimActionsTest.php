<?php

use App\Actions\AtenderRequisicaoMaterialAction;
use App\Actions\RecusarRequisicaoMaterialAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicaoMaterial;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * @return array{unidade: Unidade, saldo: SaldoEstoque, almoxarife: User, solicitante: User, rim: RequisicaoMaterial}
 */
function rim_setup(float $quantidade = 10.0, float $cmp = 50.0): array
{
    $unidade = Unidade::factory()->create();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => 'Material de Teste',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material de Teste'),
        'unidade_medida' => 'un',
        'quantidade' => $quantidade,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $quantidade * $cmp,
    ]);

    $rim = RequisicaoMaterial::create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'saldo_estoque_id' => $saldo->id,
        'quantidade_solicitada' => 3.0,
        'justificativa' => 'Necessidade de uso imediato',
        'status' => StatusRequisicaoMaterial::Aberta,
    ]);

    return compact('unidade', 'saldo', 'almoxarife', 'solicitante', 'rim');
}

// ─── AtenderRequisicaoMaterialAction ────────────────────────────────────────

it('rim_atender_baixa_saldo_e_gera_movimentacao_saida', function () {
    $setup = rim_setup(quantidade: 10.0);
    $rim = $setup['rim'];
    $almoxarife = $setup['almoxarife'];
    $saldo = $setup['saldo'];

    $saldoAntes = (float) $saldo->quantidade;

    $rimAtendida = app(AtenderRequisicaoMaterialAction::class)->execute($rim, $almoxarife);

    expect($rimAtendida->status)->toBe(StatusRequisicaoMaterial::Atendida)
        ->and($rimAtendida->almoxarife_id)->toBe($almoxarife->id)
        ->and($rimAtendida->atendida_em)->not->toBeNull()
        ->and($rimAtendida->movimentacao_estoque_id)->not->toBeNull();

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe($saldoAntes - 3.0);

    // Movimentação deve ser do tipo Saída com requisicao_material_id preenchido
    $mov = MovimentacaoEstoque::find($rimAtendida->movimentacao_estoque_id);
    expect($mov)->not->toBeNull()
        ->and($mov->tipo)->toBe(TipoMovimentacao::Saida)
        ->and($mov->requisicao_material_id)->toBe($rim->id)
        ->and((float) $mov->quantidade)->toBe(3.0);
});

it('rim_atender_com_saldo_insuficiente_mantem_aberta_e_nao_altera_saldo', function () {
    $setup = rim_setup(quantidade: 2.0);
    $rim = $setup['rim']; // solicita 3.0, saldo é 2.0
    $almoxarife = $setup['almoxarife'];
    $saldo = $setup['saldo'];

    $saldoAntes = (float) $saldo->quantidade;

    expect(fn () => app(AtenderRequisicaoMaterialAction::class)->execute($rim, $almoxarife))
        ->toThrow(ValidationException::class);

    $rim->refresh();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Aberta);

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe($saldoAntes);
});

it('rim_atender_rejeita_rim_nao_aberta', function () {
    $setup = rim_setup();
    $rim = $setup['rim'];
    $almoxarife = $setup['almoxarife'];

    // Forçar status atendida
    $rim->update(['status' => StatusRequisicaoMaterial::Atendida, 'atendida_em' => now()]);

    expect(fn () => app(AtenderRequisicaoMaterialAction::class)->execute($rim, $almoxarife))
        ->toThrow(ValidationException::class);
});

it('rim_atender_rejeita_almoxarife_de_outra_unidade', function () {
    $setup = rim_setup();
    $rim = $setup['rim'];

    $outraUnidade = Unidade::factory()->create();
    $outroAlmoxarife = User::factory()->create();
    $outroAlmoxarife->unidades()->attach($outraUnidade->id, ['perfil' => Perfil::Almoxarife->value]);

    expect(fn () => app(AtenderRequisicaoMaterialAction::class)->execute($rim, $outroAlmoxarife))
        ->toThrow(ValidationException::class);

    $rim->refresh();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Aberta);
});

// ─── RecusarRequisicaoMaterialAction ────────────────────────────────────────

it('rim_recusar_exige_motivo_preenchido', function () {
    $setup = rim_setup();
    $rim = $setup['rim'];
    $almoxarife = $setup['almoxarife'];

    expect(fn () => app(RecusarRequisicaoMaterialAction::class)->execute($rim, $almoxarife, ''))
        ->toThrow(ValidationException::class);

    $rim->refresh();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Aberta);
});

it('rim_recusar_seta_status_recusada_e_salva_motivo', function () {
    $setup = rim_setup();
    $rim = $setup['rim'];
    $almoxarife = $setup['almoxarife'];

    $rimRecusada = app(RecusarRequisicaoMaterialAction::class)->execute($rim, $almoxarife, 'Item indisponível no período.');

    expect($rimRecusada->status)->toBe(StatusRequisicaoMaterial::Recusada)
        ->and($rimRecusada->almoxarife_id)->toBe($almoxarife->id)
        ->and($rimRecusada->motivo_recusa)->toBe('Item indisponível no período.')
        ->and($rimRecusada->recusada_em)->not->toBeNull();
});

it('rim_recusar_nao_altera_saldo_do_estoque', function () {
    $setup = rim_setup(quantidade: 10.0);
    $rim = $setup['rim'];
    $almoxarife = $setup['almoxarife'];
    $saldo = $setup['saldo'];

    $saldoAntes = (float) $saldo->quantidade;

    app(RecusarRequisicaoMaterialAction::class)->execute($rim, $almoxarife, 'Motivo válido.');

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe($saldoAntes);
});

it('rim_recusar_rejeita_rim_nao_aberta', function () {
    $setup = rim_setup();
    $rim = $setup['rim'];
    $almoxarife = $setup['almoxarife'];

    $rim->update(['status' => StatusRequisicaoMaterial::Recusada, 'recusada_em' => now(), 'motivo_recusa' => 'Teste']);

    expect(fn () => app(RecusarRequisicaoMaterialAction::class)->execute($rim, $almoxarife, 'Novo motivo'))
        ->toThrow(ValidationException::class);
});

it('rim_recusar_rejeita_almoxarife_de_outra_unidade', function () {
    $setup = rim_setup();
    $rim = $setup['rim'];

    $outraUnidade = Unidade::factory()->create();
    $outroAlmoxarife = User::factory()->create();
    $outroAlmoxarife->unidades()->attach($outraUnidade->id, ['perfil' => Perfil::Almoxarife->value]);

    expect(fn () => app(RecusarRequisicaoMaterialAction::class)->execute($rim, $outroAlmoxarife, 'Motivo'))
        ->toThrow(ValidationException::class);
});
