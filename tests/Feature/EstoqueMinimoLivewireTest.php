<?php

use App\Actions\DefinirEstoqueMinimoAction;
use App\Enums\Perfil;
use App\Livewire\Admin\CatalogoItens\ListaCatalogoItens;
use App\Livewire\Almoxarife\SaldosEstoque;
use App\Livewire\Compradora\ItensARepor;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @return array{unidade: Unidade, almoxarife: User, item: CatalogoItem, saldo: SaldoEstoque}
 */
function emLw_setup(): array
{
    $unidade = Unidade::factory()->create();
    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value, 'nivel_alcada' => null]);
    $item = CatalogoItem::factory()->create(['descricao' => 'Parafuso M8', 'unidade_medida' => 'un']);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $item->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($item->descricao),
        'unidade_medida' => $item->unidade_medida,
        'quantidade' => 3.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 30.0,
        'item_catalogo_id' => $item->id,
    ]);

    return compact('unidade', 'almoxarife', 'item', 'saldo');
}

// ─── PASSO 3 — SaldosEstoque (badge + painel + modal) ─────────────────────────

it('saldos_estoque_badge_aparece_quando_saldo_abaixo_do_minimo', function () {
    $setup = emLw_setup();
    $admin = User::factory()->admin()->create();

    // Define mínimo maior que o saldo (3 < 10)
    app(DefinirEstoqueMinimoAction::class)->execute($setup['unidade'], $setup['item'], 10.0, $admin);

    Livewire::actingAs($setup['almoxarife'])
        ->test(SaldosEstoque::class)
        ->assertSee('Abaixo do mínimo');
});

it('saldos_estoque_badge_nao_aparece_quando_saldo_suficiente', function () {
    $setup = emLw_setup();
    $admin = User::factory()->admin()->create();

    // Saldo (3) abaixo do mínimo (10), mas vamos colocar o saldo alto
    $setup['saldo']->update(['quantidade' => 15.0, 'valor_total' => 150.0]);
    app(DefinirEstoqueMinimoAction::class)->execute($setup['unidade'], $setup['item'], 10.0, $admin);

    Livewire::actingAs($setup['almoxarife'])
        ->test(SaldosEstoque::class)
        ->assertDontSee('Abaixo do mínimo');
});

it('saldos_estoque_modal_salvar_minimo_persiste', function () {
    $setup = emLw_setup();

    Livewire::actingAs($setup['almoxarife'])
        ->test(SaldosEstoque::class)
        ->call('abrirModalMinimo', $setup['saldo']->id)
        ->assertSet('mostrarModalMinimo', true)
        ->set('minimoQuantidade', '8')
        ->call('salvarMinimo')
        ->assertSet('mostrarModalMinimo', false);

    expect(EstoqueMinimo::count())->toBe(1)
        ->and((float) EstoqueMinimo::first()->quantidade_minima)->toBe(8.0);
});

it('saldos_estoque_modal_salvar_minimo_zero_remove', function () {
    $setup = emLw_setup();
    $admin = User::factory()->admin()->create();

    // Cria mínimo primeiro
    app(DefinirEstoqueMinimoAction::class)->execute($setup['unidade'], $setup['item'], 10.0, $admin);
    expect(EstoqueMinimo::count())->toBe(1);

    Livewire::actingAs($setup['almoxarife'])
        ->test(SaldosEstoque::class)
        ->call('abrirModalMinimo', $setup['saldo']->id)
        ->set('minimoQuantidade', '0')
        ->call('salvarMinimo');

    expect(EstoqueMinimo::count())->toBe(0);
});

it('saldos_estoque_painel_lista_itens_a_repor_da_unidade', function () {
    $setup = emLw_setup();
    $admin = User::factory()->admin()->create();

    // Saldo (3) abaixo do mínimo (10)
    app(DefinirEstoqueMinimoAction::class)->execute($setup['unidade'], $setup['item'], 10.0, $admin);

    Livewire::actingAs($setup['almoxarife'])
        ->test(SaldosEstoque::class)
        ->assertSee('Itens a repor')
        ->assertSee($setup['item']->descricao);
});

it('saldos_estoque_almoxarife_outra_unidade_nao_ve_saldos_nem_define_minimo', function () {
    $unidade1 = Unidade::factory()->create();
    $unidade2 = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();

    $almoxarife2 = User::factory()->create();
    $almoxarife2->unidades()->attach($unidade2->id, ['perfil' => Perfil::Almoxarife->value, 'nivel_alcada' => null]);

    SaldoEstoque::create([
        'unidade_id' => $unidade1->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $item->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($item->descricao),
        'unidade_medida' => 'un',
        'quantidade' => 0.0,
        'custo_medio_ponderado' => 0.0,
        'valor_total' => 0.0,
        'item_catalogo_id' => $item->id,
    ]);

    $admin = User::factory()->admin()->create();
    app(DefinirEstoqueMinimoAction::class)->execute($unidade1, $item, 10.0, $admin);

    // Almoxarife da unidade2 não vê saldos da unidade1
    $component = Livewire::actingAs($almoxarife2)
        ->test(SaldosEstoque::class);

    $saldos = $component->viewData('saldos');
    expect($saldos->total())->toBe(0);

    // Tenta salvar mínimo para unidade1 diretamente via state (simula request forjado)
    // A ação deve barrar porque almoxarife2 não tem vínculo com unidade1
    $component
        ->set('minimoUnidadeId', (string) $unidade1->id)
        ->set('minimoItemCatalogoId', $item->id)
        ->set('minimoDescricaoItem', $item->descricao)
        ->set('mostrarModalMinimo', true)
        ->set('minimoQuantidade', '5')
        ->call('salvarMinimo')
        ->assertHasErrors(['minimoQuantidade']);
});

it('saldos_estoque_403_sem_perfil_almoxarife', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('almoxarife.estoque.index'))
        ->assertForbidden();
});

// ─── PASSO 4 — ListaCatalogoItens: modal Mínimos por Unidade (Admin) ──────────

it('catalogo_admin_define_minimo_via_modal_minimos', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirModalMinimos', $item->id)
        ->assertSet('mostrarModalMinimos', true);

    // Localiza o índice da unidade no array minimosPorUnidade
    $minimosPorUnidade = $component->get('minimosPorUnidade');
    $idx = collect($minimosPorUnidade)->search(fn ($m) => (int) $m['unidade_id'] === $unidade->id);
    expect($idx)->not->toBeFalse();

    $component
        ->set("minimosPorUnidade.{$idx}.quantidade_minima", '15')
        ->call('salvarMinimoUnidade', $unidade->id);

    expect(EstoqueMinimo::count())->toBe(1)
        ->and((float) EstoqueMinimo::first()->quantidade_minima)->toBe(15.0);
});

it('catalogo_admin_zero_remove_minimo_via_modal', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();

    // Cria mínimo primeiro
    app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 20.0, $admin);
    expect(EstoqueMinimo::count())->toBe(1);

    $component = Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirModalMinimos', $item->id);

    $minimosPorUnidade = $component->get('minimosPorUnidade');
    $idx = collect($minimosPorUnidade)->search(fn ($m) => (int) $m['unidade_id'] === $unidade->id);

    $component
        ->set("minimosPorUnidade.{$idx}.quantidade_minima", '0')
        ->call('salvarMinimoUnidade', $unidade->id);

    expect(EstoqueMinimo::count())->toBe(0);
});

it('catalogo_403_nao_admin_nao_acessa_catalogo', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('admin.catalogo-itens'))
        ->assertForbidden();
});

// ─── PASSO 5 — Compradora\ItensARepor ─────────────────────────────────────────

it('compradora_ve_itens_a_repor_da_rede', function () {
    $unidade1 = Unidade::factory()->create(['nome' => 'Unidade Alpha']);
    $unidade2 = Unidade::factory()->create(['nome' => 'Unidade Beta']);
    $item = CatalogoItem::factory()->create(['descricao' => 'Luva EPI']);
    $admin = User::factory()->admin()->create();
    $compradora = User::factory()->compradora()->create();

    app(DefinirEstoqueMinimoAction::class)->execute($unidade1, $item, 10.0, $admin);
    app(DefinirEstoqueMinimoAction::class)->execute($unidade2, $item, 5.0, $admin);

    $component = Livewire::actingAs($compradora)
        ->test(ItensARepor::class);

    $itensPorUnidade = $component->viewData('itensPorUnidade');
    expect($itensPorUnidade)->toHaveCount(2);
});

it('compradora_itens_a_repor_403_para_usuario_comum', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('compradora.itens-a-repor'))
        ->assertForbidden();
});

it('compradora_itens_a_repor_empty_state', function () {
    $compradora = User::factory()->compradora()->create();

    Livewire::actingAs($compradora)
        ->test(ItensARepor::class)
        ->assertSee('Nenhum item abaixo do estoque mínimo encontrado');
});

it('compradora_solicitar_reposicao_redireciona_com_query_correta', function () {
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();
    $admin = User::factory()->admin()->create();
    $compradora = User::factory()->compradora()->create();

    app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);

    Livewire::actingAs($compradora)
        ->test(ItensARepor::class)
        ->call('solicitarReposicao', $unidade->id, $item->id, 10.0)
        ->assertRedirect(
            route('requisicoes.criar', [
                'item_catalogo_id' => $item->id,
                'unidade_id' => $unidade->id,
                'quantidade_sugerida' => 10.0,
            ])
        );
});

// ─── PASSO 6 — Pré-preenchimento do FormularioRequisicao ──────────────────────

it('formulario_requisicao_preenchido_com_item_catalogo_via_query', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);
    $item = CatalogoItem::factory()->create(['descricao' => 'Luva de proteção', 'unidade_medida' => 'par']);

    $component = Livewire::actingAs($solicitante)
        ->withQueryParams([
            'item_catalogo_id' => $item->id,
            'unidade_id' => $unidade->id,
            'quantidade_sugerida' => 12.0,
        ])
        ->test(FormularioRequisicao::class);

    $itens = $component->get('itens');

    expect($itens)->toHaveCount(1)
        ->and($itens[0]['item_catalogo_id'])->toBe($item->id)
        ->and($itens[0]['avulso'])->toBeFalse()
        ->and($itens[0]['descricao'])->toBe('Luva de proteção')
        ->and($itens[0]['unidade_medida'])->toBe('par')
        ->and((float) $itens[0]['quantidade'])->toBe(12.0);
});

it('formulario_requisicao_query_com_unidade_fora_da_visibilidade_usa_default', function () {
    $unidade1 = Unidade::factory()->create();
    $unidade2 = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade1->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);
    $item = CatalogoItem::factory()->create();

    $component = Livewire::actingAs($solicitante)
        ->withQueryParams([
            'item_catalogo_id' => $item->id,
            'unidade_id' => $unidade2->id, // unidade2 fora da visibilidade do solicitante
            'quantidade_sugerida' => 5.0,
        ])
        ->test(FormularioRequisicao::class);

    // Deve cair no default (unidade1, a primeira do usuário)
    expect($component->get('unidadeId'))->toBe($unidade1->id);
});

it('formulario_requisicao_sem_query_mantem_comportamento_atual', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);

    $component = Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class);

    $itens = $component->get('itens');

    expect($itens)->toHaveCount(1)
        ->and($itens[0]['avulso'])->toBeTrue()
        ->and($itens[0]['item_catalogo_id'])->toBeNull()
        ->and($itens[0]['descricao'])->toBe('');
});

// ─── Pós-sec/QA — fechamento de ressalvas ─────────────────────────────────────

it('compradora_solicitar_reposicao_combinacao_fora_de_alerta_aborta', function () {
    // P1-C: redirect só é permitido para (unidade, item) realmente em alerta
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();
    $compradora = User::factory()->compradora()->create();
    // nenhum mínimo definido → item NÃO está em alerta

    Livewire::actingAs($compradora)
        ->test(ItensARepor::class)
        ->call('solicitarReposicao', $unidade->id, $item->id, 5.0)
        ->assertStatus(404);
});

it('formulario_requisicao_sugestao_fracionaria_menor_que_1_vira_1', function () {
    // Desvio max(1.0, sugerida): sugestão fracionária < 1 é elevada a 1 (validação min:0.001)
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);
    $item = CatalogoItem::factory()->create();

    $component = Livewire::actingAs($solicitante)
        ->withQueryParams([
            'item_catalogo_id' => $item->id,
            'unidade_id' => $unidade->id,
            'quantidade_sugerida' => 0.1,
        ])
        ->test(FormularioRequisicao::class);

    expect((float) $component->get('itens')[0]['quantidade'])->toBe(1.0);
});

it('formulario_requisicao_item_catalogo_inativo_no_query_param_cai_no_avulso', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);
    $itemInativo = CatalogoItem::factory()->create(['ativo' => false]);

    $component = Livewire::actingAs($solicitante)
        ->withQueryParams([
            'item_catalogo_id' => $itemInativo->id,
            'unidade_id' => $unidade->id,
            'quantidade_sugerida' => 5.0,
        ])
        ->test(FormularioRequisicao::class);

    $itens = $component->get('itens');
    expect($itens[0]['avulso'])->toBeTrue()
        ->and($itens[0]['item_catalogo_id'])->toBeNull();
});
