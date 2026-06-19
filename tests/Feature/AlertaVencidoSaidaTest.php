<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Enums\StatusRequisicaoMaterial;
use App\Livewire\Almoxarife\AtendimentoRequisicoesMaterial;
use App\Livewire\Compradora\TriagemRequisicoes;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\ItemRequisicao;
use App\Models\LoteEstoque;
use App\Models\Requisicao;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Monta unidade + saldo (controla_lote opcional) com lotes, uma requisição em triagem
 * apontando para o item e os usuários necessários.
 *
 * @param  array<int, ?string>  $validades  uma validade por lote (vazio = sem lote)
 */
function av_setup(array $validades = [], bool $controlaLote = true, float $qtdReq = 3.0): array
{
    $unidade = Unidade::factory()->create();
    $compradora = User::factory()->compradora()->create();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $catalogo = CatalogoItem::factory()->create([
        'descricao' => 'Insumo Controlado',
        'controla_lote' => $controlaLote,
    ]);

    $total = max(count($validades) * 5.0, 10.0);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => $total,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => $total * 10.0,
        'item_catalogo_id' => $catalogo->id,
    ]);

    foreach ($validades as $i => $val) {
        LoteEstoque::factory()->create([
            'saldo_estoque_id' => $saldo->id,
            'numero_lote' => 'L-'.$i,
            'validade' => $val,
            'quantidade' => 5.0,
            'fundido_para_id' => null,
        ]);
    }

    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoTriagem,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-AV-'.fake()->unique()->numerify('#####'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => $catalogo->descricao,
        'quantidade' => $qtdReq,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => 10.0,
        'item_catalogo_id' => $catalogo->id,
        'avulso' => false,
    ]);

    return compact('unidade', 'compradora', 'almoxarife', 'solicitante', 'catalogo', 'saldo', 'requisicao');
}

function av_criarRim(array $setup, float $qtd = 2.0): RequisicaoMaterial
{
    return RequisicaoMaterial::create([
        'unidade_id' => $setup['unidade']->id,
        'solicitante_id' => $setup['solicitante']->id,
        'saldo_estoque_id' => $setup['saldo']->id,
        'quantidade_solicitada' => $qtd,
        'justificativa' => 'Uso urgente do material',
        'status' => StatusRequisicaoMaterial::Aberta,
    ]);
}

// ─── TriagemRequisicoes::temLoteVencido ───────────────────────────────────────

it('triagem_alerta_vencido_true_quando_item_controla_lote_tem_lote_vencido', function () {
    // Hoje = 2026-06-19: 2025-01-01 vencido, 2027-01-01 não.
    $s = av_setup(validades: ['2025-01-01', '2027-01-01']);
    $req = $s['requisicao']->load('itens');

    expect((new TriagemRequisicoes)->temLoteVencido($req))->toBeTrue();
});

it('triagem_alerta_vencido_false_quando_lotes_sao_futuros', function () {
    $s = av_setup(validades: ['2027-01-01', '2028-01-01']);
    $req = $s['requisicao']->load('itens');

    expect((new TriagemRequisicoes)->temLoteVencido($req))->toBeFalse();
});

it('triagem_alerta_vencido_false_quando_saldo_sem_lote', function () {
    $s = av_setup(validades: []);   // controla_lote true, mas nenhum lote
    $req = $s['requisicao']->load('itens');

    expect((new TriagemRequisicoes)->temLoteVencido($req))->toBeFalse();
});

it('triagem_alerta_vencido_false_quando_item_nao_controla_lote', function () {
    $s = av_setup(validades: [], controlaLote: false);
    $req = $s['requisicao']->load('itens');

    expect((new TriagemRequisicoes)->temLoteVencido($req))->toBeFalse();
});

it('triagem_render_mostra_badge_vencido', function () {
    $s = av_setup(validades: ['2025-01-01']);

    Livewire::actingAs($s['compradora'])
        ->test(TriagemRequisicoes::class)
        ->assertSee('Vencido');
});

// ─── AtendimentoRequisicoesMaterial (RIM) ─────────────────────────────────────

it('atendimento_rim_mostra_badge_para_saldo_com_lote_vencido', function () {
    $s = av_setup(validades: ['2025-01-01']);
    av_criarRim($s);

    Livewire::actingAs($s['almoxarife'])
        ->test(AtendimentoRequisicoesMaterial::class)
        ->assertSee('Vencido');
});

it('atendimento_rim_sem_badge_quando_lote_futuro', function () {
    $s = av_setup(validades: ['2027-01-01']);
    av_criarRim($s);

    Livewire::actingAs($s['almoxarife'])
        ->test(AtendimentoRequisicoesMaterial::class)
        ->assertDontSee('Vencido');
});
