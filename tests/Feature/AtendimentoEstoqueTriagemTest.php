<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\TriagemRequisicoes;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\ItemRequisicao;
use App\Models\MovimentacaoEstoque;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * @return array{unidade: Unidade, compradora: User, catalogo: CatalogoItem, saldo: SaldoEstoque, requisicao: Requisicao}
 */
function at7c_setup(
    StatusRequisicao $status = StatusRequisicao::AguardandoTriagem,
    float $qtdReq = 3.0,
    float $qtdSaldo = 10.0,
    bool $avulso = false,
): array {
    $unidade = Unidade::factory()->create();
    $compradora = User::factory()->compradora()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $catalogo = CatalogoItem::factory()->create(['descricao' => 'Item Teste 7C']);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => $qtdSaldo,
        'custo_medio_ponderado' => 30.0,
        'valor_total' => $qtdSaldo * 30.0,
        'item_catalogo_id' => $catalogo->id,
    ]);

    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => $status,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-7C-'.fake()->unique()->numerify('####'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => $catalogo->descricao,
        'quantidade' => $qtdReq,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => 30.0,
        'item_catalogo_id' => $avulso ? null : $catalogo->id,
        'avulso' => $avulso,
    ]);

    return compact('unidade', 'compradora', 'catalogo', 'saldo', 'requisicao');
}

// ─── todosItensTemSaldo ──────────────────────────────────────────────────────

it('7c_todos_itens_tem_saldo_retorna_true_quando_ha_saldo', function () {
    $setup = at7c_setup(qtdReq: 3.0, qtdSaldo: 10.0);
    $requisicao = $setup['requisicao']->load('itens');

    $component = new TriagemRequisicoes;
    expect($component->todosItensTemSaldo($requisicao))->toBeTrue();
});

it('7c_todos_itens_tem_saldo_retorna_false_quando_saldo_insuficiente', function () {
    $setup = at7c_setup(qtdReq: 15.0, qtdSaldo: 5.0); // solicita mais do que disponível
    $requisicao = $setup['requisicao']->load('itens');

    $component = new TriagemRequisicoes;
    expect($component->todosItensTemSaldo($requisicao))->toBeFalse();
});

it('7c_todos_itens_tem_saldo_retorna_false_para_item_avulso', function () {
    $setup = at7c_setup(avulso: true);
    $requisicao = $setup['requisicao']->load('itens');

    $component = new TriagemRequisicoes;
    expect($component->todosItensTemSaldo($requisicao))->toBeFalse();
});

// ─── atenderDoEstoque ────────────────────────────────────────────────────────

it('7c_atender_do_estoque_conclui_requisicao_e_baixa_saldo', function () {
    $setup = at7c_setup(status: StatusRequisicao::AguardandoTriagem, qtdReq: 3.0, qtdSaldo: 10.0);
    $requisicao = $setup['requisicao'];
    $saldo = $setup['saldo'];

    Livewire::actingAs($setup['compradora'])
        ->test(TriagemRequisicoes::class)
        ->call('atenderDoEstoque', $requisicao->id);

    $requisicao->refresh();
    expect($requisicao->status)->toBe(StatusRequisicao::Concluida);

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe(7.0);

    // Gerou uma movimentação de saída por item
    expect(MovimentacaoEstoque::count())->toBe(1);
});

it('7c_atender_do_estoque_em_triagem_tambem_funciona', function () {
    $setup = at7c_setup(status: StatusRequisicao::EmTriagem, qtdReq: 2.0, qtdSaldo: 5.0);
    $requisicao = $setup['requisicao'];

    Livewire::actingAs($setup['compradora'])
        ->test(TriagemRequisicoes::class)
        ->call('atenderDoEstoque', $requisicao->id);

    $requisicao->refresh();
    expect($requisicao->status)->toBe(StatusRequisicao::Concluida);
});

it('7c_atender_requisicao_avulsa_exibe_erro_e_nao_conclui', function () {
    $setup = at7c_setup(avulso: true, qtdReq: 1.0, qtdSaldo: 10.0);
    $requisicao = $setup['requisicao'];

    $component = Livewire::actingAs($setup['compradora'])
        ->test(TriagemRequisicoes::class)
        ->call('atenderDoEstoque', $requisicao->id);

    expect($component->get('erroAtendimentoEstoque'))->not->toBeEmpty();

    $requisicao->refresh();
    expect($requisicao->status)->toBe(StatusRequisicao::AguardandoTriagem);
});

it('7c_botao_atender_do_estoque_ausente_quando_falta_saldo', function () {
    $setup = at7c_setup(qtdReq: 20.0, qtdSaldo: 5.0); // requisita mais que disponível

    Livewire::actingAs($setup['compradora'])
        ->test(TriagemRequisicoes::class)
        ->assertDontSee('Atender do Estoque');
});

it('7c_compradora_sem_perfil_403', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('compradora.triagem'))
        ->assertForbidden();
});

it('7c_atender_do_estoque_rollback_total_quando_um_item_sem_saldo', function () {
    $unidade = Unidade::factory()->create();
    $compradora = User::factory()->compradora()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $cat1 = CatalogoItem::factory()->create(['descricao' => 'Item Suficiente']);
    $cat2 = CatalogoItem::factory()->create(['descricao' => 'Item Insuficiente']);

    $saldo1 = SaldoEstoque::create([
        'unidade_id' => $unidade->id, 'deposito' => 'Depósito Central',
        'descricao_item' => $cat1->descricao, 'descricao_normalizada' => SaldoEstoque::normalizarDescricao($cat1->descricao),
        'unidade_medida' => 'un', 'quantidade' => 10.0, 'custo_medio_ponderado' => 30.0, 'valor_total' => 300.0,
        'item_catalogo_id' => $cat1->id,
    ]);
    $saldo2 = SaldoEstoque::create([
        'unidade_id' => $unidade->id, 'deposito' => 'Depósito Central',
        'descricao_item' => $cat2->descricao, 'descricao_normalizada' => SaldoEstoque::normalizarDescricao($cat2->descricao),
        'unidade_medida' => 'un', 'quantidade' => 2.0, 'custo_medio_ponderado' => 30.0, 'valor_total' => 60.0,
        'item_catalogo_id' => $cat2->id,
    ]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id, 'unidade_id' => $unidade->id, 'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoTriagem, 'urgente' => false, 'is_emergencial' => false,
        'codigo' => 'REQ-7C-'.fake()->unique()->numerify('####'), 'submetida_em' => now()->subHour(), 'ciclo_aprovacao' => 1,
    ]);
    ItemRequisicao::create([
        'requisicao_id' => $requisicao->id, 'descricao' => $cat1->descricao, 'quantidade' => 3.0,
        'unidade_medida' => 'un', 'valor_unitario_estimado' => 30.0, 'item_catalogo_id' => $cat1->id, 'avulso' => false,
    ]);
    ItemRequisicao::create([
        'requisicao_id' => $requisicao->id, 'descricao' => $cat2->descricao, 'quantidade' => 5.0, // > saldo2 (2)
        'unidade_medida' => 'un', 'valor_unitario_estimado' => 30.0, 'item_catalogo_id' => $cat2->id, 'avulso' => false,
    ]);

    Livewire::actingAs($compradora)
        ->test(TriagemRequisicoes::class)
        ->call('atenderDoEstoque', $requisicao->id);

    // Rollback total: nenhum saldo baixado, nenhuma movimentação, requisição segue aguardando
    expect((float) $saldo1->refresh()->quantidade)->toBe(10.0)
        ->and((float) $saldo2->refresh()->quantidade)->toBe(2.0)
        ->and(MovimentacaoEstoque::count())->toBe(0)
        ->and($requisicao->refresh()->status)->toBe(StatusRequisicao::AguardandoTriagem);
});
