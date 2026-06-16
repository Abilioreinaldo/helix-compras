<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicaoMaterial;
use App\Livewire\Almoxarife\AtendimentoRequisicoesMaterial;
use App\Livewire\Solicitante\RequisicoesMaterial;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * @return array{unidade: Unidade, saldo: SaldoEstoque, almoxarife: User, solicitante: User}
 */
function rim_lw_setup(float $quantidade = 10.0): array
{
    $unidade = Unidade::factory()->create();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => 'Item de Teste RIM',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Item de Teste RIM'),
        'unidade_medida' => 'un',
        'quantidade' => $quantidade,
        'custo_medio_ponderado' => 50.0,
        'valor_total' => $quantidade * 50.0,
    ]);

    return compact('unidade', 'saldo', 'almoxarife', 'solicitante');
}

function rim_lw_criar(array $setup, float $qtd = 3.0): RequisicaoMaterial
{
    return RequisicaoMaterial::create([
        'unidade_id' => $setup['unidade']->id,
        'solicitante_id' => $setup['solicitante']->id,
        'saldo_estoque_id' => $setup['saldo']->id,
        'quantidade_solicitada' => $qtd,
        'justificativa' => 'Necessidade urgente de uso',
        'status' => StatusRequisicaoMaterial::Aberta,
    ]);
}

// ─── Solicitante: abrir RIM ───────────────────────────────────────────────────

it('rim_solicitante_pode_abrir_nova_requisicao', function () {
    $setup = rim_lw_setup(quantidade: 10.0);

    Livewire::actingAs($setup['solicitante'])
        ->test(RequisicoesMaterial::class)
        ->call('abrirFormulario')
        ->set('saldoEstoqueId', $setup['saldo']->id)
        ->set('quantidadeSolicitada', '5')
        ->set('justificativa', 'Preciso para o trabalho')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(RequisicaoMaterial::count())->toBe(1);
    $rim = RequisicaoMaterial::first();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Aberta)
        ->and($rim->solicitante_id)->toBe($setup['solicitante']->id)
        ->and((float) $rim->quantidade_solicitada)->toBe(5.0);
});

it('rim_solicitante_ve_suas_proprias_requisicoes_com_status', function () {
    $setup = rim_lw_setup();
    $rim = rim_lw_criar($setup);

    Livewire::actingAs($setup['solicitante'])
        ->test(RequisicoesMaterial::class)
        ->assertSee('Aberta')
        ->assertSee($rim->justificativa);
});

it('rim_solicitante_403_sem_perfil', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('solicitante.rim.index'))
        ->assertForbidden();
});

// ─── Almoxarife: atender RIM ─────────────────────────────────────────────────

it('rim_almoxarife_atender_baixa_saldo', function () {
    $setup = rim_lw_setup(quantidade: 10.0);
    $rim = rim_lw_criar($setup, qtd: 3.0);
    $saldo = $setup['saldo'];

    Livewire::actingAs($setup['almoxarife'])
        ->test(AtendimentoRequisicoesMaterial::class)
        ->call('atender', $rim->id);

    $rim->refresh();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Atendida);

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe(7.0);
});

it('rim_almoxarife_recusar_com_motivo_seta_recusada', function () {
    $setup = rim_lw_setup();
    $rim = rim_lw_criar($setup);

    Livewire::actingAs($setup['almoxarife'])
        ->test(AtendimentoRequisicoesMaterial::class)
        ->call('abrirRecusa', $rim->id)
        ->set('motivoRecusa', 'Item em manutenção no período.')
        ->call('confirmarRecusa');

    $rim->refresh();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Recusada)
        ->and($rim->motivo_recusa)->toBe('Item em manutenção no período.');
});

it('rim_almoxarife_erro_saldo_insuficiente_exibe_mensagem_e_rim_segue_aberta', function () {
    $setup = rim_lw_setup(quantidade: 2.0);
    $rim = rim_lw_criar($setup, qtd: 5.0); // solicita mais que disponível

    $component = Livewire::actingAs($setup['almoxarife'])
        ->test(AtendimentoRequisicoesMaterial::class)
        ->call('atender', $rim->id);

    // erroAtendimento deve ser populado
    expect($component->get('erroAtendimento'))->not->toBeEmpty();

    $rim->refresh();
    expect($rim->status)->toBe(StatusRequisicaoMaterial::Aberta);
});

it('rim_almoxarife_de_outra_unidade_nao_ve_rim_da_unidade_diferente', function () {
    $setup = rim_lw_setup();
    rim_lw_criar($setup);

    $outraUnidade = Unidade::factory()->create();
    $outroAlmoxarife = User::factory()->create();
    $outroAlmoxarife->unidades()->attach($outraUnidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $component = Livewire::actingAs($outroAlmoxarife)
        ->test(AtendimentoRequisicoesMaterial::class);

    // Deve exibir lista vazia (sem as RIMs da outra unidade)
    expect($component->viewData('requisicoes')->total())->toBe(0);
});

it('rim_almoxarife_403_sem_perfil', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('almoxarife.rim.index'))
        ->assertForbidden();
});

it('rim_solicitante_nao_pode_criar_rim_em_unidade_alheia', function () {
    $setup = rim_lw_setup();

    // Saldo numa OUTRA unidade, à qual o solicitante não pertence
    $outraUnidade = Unidade::factory()->create();
    $saldoAlheio = SaldoEstoque::create([
        'unidade_id' => $outraUnidade->id,
        'deposito' => 'Depósito Alheio',
        'descricao_item' => 'Item de Outra Unidade',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Item de Outra Unidade'),
        'unidade_medida' => 'un',
        'quantidade' => 50.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 500.0,
    ]);

    // O findOrFail escopado às unidades do solicitante barra o saldo alheio
    expect(function () use ($setup, $saldoAlheio) {
        Livewire::actingAs($setup['solicitante'])
            ->test(RequisicoesMaterial::class)
            ->call('abrirFormulario')
            ->set('saldoEstoqueId', $saldoAlheio->id)
            ->set('quantidadeSolicitada', '1')
            ->set('justificativa', 'Tentativa indevida')
            ->call('salvar');
    })->toThrow(ModelNotFoundException::class);

    expect(RequisicaoMaterial::count())->toBe(0);
});
