<?php

use App\Actions\SaidaEstoqueAction;
use App\Enums\Perfil;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function b1_saldo(float $quantidade = 10.0): SaldoEstoque
{
    $unidade = Unidade::factory()->create();

    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => 'Produto B1',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Produto B1'),
        'unidade_medida' => 'un',
        'quantidade' => $quantidade,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => $quantidade * 10.0,
    ]);
}

function b1_usuarioComPerfilNaUnidade(int $unidadeId, Perfil $perfil): User
{
    $user = User::factory()->create();
    $user->unidades()->attach($unidadeId, ['perfil' => $perfil->value]);

    return $user;
}

// ─── Autorização de saída (B1 com contexto de atendimento direto) ─────────────
//
// Regra: saída autorizada para Almoxarife DA unidade do saldo (saída normal), Admin
// (irrestrito) e CompradoraSenior SOMENTE no contexto de atendimento direto. Qualquer
// outro perfil — e a própria Compradora fora do contexto — é barrado.

it('saida_permitida_para_almoxarife_da_propria_unidade', function () {
    $saldo = b1_saldo(quantidade: 10.0);
    $almoxarife = b1_usuarioComPerfilNaUnidade($saldo->unidade_id, Perfil::Almoxarife);

    $mov = app(SaidaEstoqueAction::class)->execute($saldo, 4.0, 'Saída interna', $almoxarife);

    expect($mov)->not->toBeNull();
    expect((float) $saldo->refresh()->quantidade)->toBe(6.0);
});

it('saida_permitida_para_admin', function () {
    $saldo = b1_saldo(quantidade: 10.0);
    $admin = User::factory()->admin()->create();

    $mov = app(SaidaEstoqueAction::class)->execute($saldo, 3.0, 'Ajuste administrativo', $admin);

    expect($mov)->not->toBeNull();
    expect((float) $saldo->refresh()->quantidade)->toBe(7.0);
});

it('saida_permitida_para_compradora_em_atendimento_direto', function () {
    $saldo = b1_saldo(quantidade: 10.0);
    $compradora = User::factory()->compradora()->create();

    // 5º argumento true = contexto de atendimento direto
    $mov = app(SaidaEstoqueAction::class)->execute($saldo, 2.0, 'Atendimento direto', $compradora, true);

    expect($mov)->not->toBeNull();
    expect((float) $saldo->refresh()->quantidade)->toBe(8.0);
});

it('saida_barrada_para_compradora_fora_do_atendimento_direto', function () {
    $saldo = b1_saldo();
    $compradora = User::factory()->compradora()->create();

    // Sem o contexto (default false), a Compradora NÃO pode baixar saldo avulso
    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 1.0, 'Fora do fluxo', $compradora))
        ->toThrow(ValidationException::class);

    expect((float) $saldo->refresh()->quantidade)->toBe(10.0);
});

it('saida_barrada_para_almoxarife_de_outra_unidade', function () {
    $saldo = b1_saldo();
    $outraUnidade = Unidade::factory()->create();
    $outroAlmoxarife = b1_usuarioComPerfilNaUnidade($outraUnidade->id, Perfil::Almoxarife);

    // Nem mesmo com o contexto de atendimento direto — não é Compradora nem Admin
    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 1.0, 'Tentativa', $outroAlmoxarife, true))
        ->toThrow(ValidationException::class);
});

it('saida_barrada_para_solicitante', function () {
    $saldo = b1_saldo();
    $solicitante = b1_usuarioComPerfilNaUnidade($saldo->unidade_id, Perfil::Solicitante);

    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 1.0, 'Tentativa', $solicitante))
        ->toThrow(ValidationException::class);
});

it('saida_barrada_para_aprovador', function () {
    $saldo = b1_saldo();
    $aprovador = b1_usuarioComPerfilNaUnidade($saldo->unidade_id, Perfil::Aprovador);

    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 1.0, 'Tentativa', $aprovador))
        ->toThrow(ValidationException::class);
});

it('saida_barrada_para_usuario_sem_perfil', function () {
    $saldo = b1_saldo();
    $semPerfil = User::factory()->create();

    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 1.0, 'Tentativa', $semPerfil))
        ->toThrow(ValidationException::class);
});
