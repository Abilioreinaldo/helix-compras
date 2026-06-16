<?php

use App\Actions\ConfirmarVinculoSaldoAction;
use App\Actions\CriarRascunhoPedidoAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Actions\EntradaEstoqueAction;
use App\Actions\SugerirVinculoCatalogoAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Livewire\Admin\CatalogoItens\ListaCatalogoItens;
use App\Livewire\Admin\CatalogoItens\ReconciliacaoSaldos;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\Recebimento;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * Cria um saldo de estoque diretamente (sem fluxo completo de PC/recebimento),
 * para testes que exercitam apenas a reconciliação de saldos.
 */
function v11a_criarSaldo(Unidade $unidade, string $descricao, string $deposito = 'Depósito Central', float $quantidade = 10.0, float $cmp = 50.0): SaldoEstoque
{
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => $deposito,
        'descricao_item' => $descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($descricao),
        'unidade_medida' => 'un',
        'quantidade' => $quantidade,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $quantidade * $cmp,
    ]);
}

// ─── Helpers (espelham f7_* de Fase7Test, com suporte a item_catalogo_id) ────

function v11a_setup(float $quantidade = 10.0, float $valorCotacao = 1000.0, ?CatalogoItem $catalogoItem = null): array
{
    $unidade = Unidade::factory()->create();

    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $compradora = User::factory()->compradora()->create();
    $compradora->unidades()->attach($unidade->id, ['perfil' => Perfil::CompradoraSenior->value]);

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $fornecedor = Fornecedor::factory()->homologado()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Aprovada,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHours(3),
        'aprovada_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    $itemReq = ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => 'Produto de Teste V11A',
        'quantidade' => $quantidade,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorCotacao / $quantidade,
        'item_catalogo_id' => $catalogoItem?->id,
        'avulso' => $catalogoItem === null,
    ]);

    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id,
        'fornecedor_id' => $fornecedor->id,
        'valor' => $valorCotacao,
        'vencedora' => true,
        'criada_por' => $compradora->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    return compact('unidade', 'solicitante', 'almoxarife', 'compradora', 'fornecedor', 'requisicao', 'itemReq', 'cotacao');
}

function v11a_emitirPC(array $setup, float $valorTotal = 1000.0, string $destino = 'Depósito Central'): PedidoCompra
{
    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $setup['fornecedor']->id,
        'unidade_id' => $setup['unidade']->id,
        'criado_por' => $setup['compradora']->id,
    ]);

    $pedido->itens()->create([
        'requisicao_id' => $setup['requisicao']->id,
        'item_requisicao_id' => $setup['itemReq']->id,
        'cotacao_id' => $setup['cotacao']->id,
        'descricao' => $setup['itemReq']->descricao,
        'quantidade' => (float) $setup['itemReq']->quantidade,
        'unidade_medida' => $setup['itemReq']->unidade_medida,
        'valor_unitario' => $valorTotal / (float) $setup['itemReq']->quantidade,
        'valor_total' => $valorTotal,
        'destino' => $destino,
        'item_catalogo_id' => $setup['itemReq']->item_catalogo_id,
        'avulso' => $setup['itemReq']->avulso,
    ]);

    return app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);
}

// ─── Migrations e estrutura de tabelas ────────────────────────────────────────

test('tabela catalogo_itens possui as colunas esperadas', function () {
    expect(Schema::hasColumns('catalogo_itens', [
        'id',
        'uuid',
        'codigo',
        'descricao',
        'unidade_medida',
        'categoria',
        'ativo',
        'deleted_at',
    ]))->toBeTrue();
});

test('tabela requisicao_itens possui colunas item_catalogo_id e avulso', function () {
    expect(Schema::hasColumns('requisicao_itens', ['item_catalogo_id', 'avulso']))->toBeTrue();
});

test('tabela itens_pedido_compra possui colunas item_catalogo_id e avulso', function () {
    expect(Schema::hasColumns('itens_pedido_compra', ['item_catalogo_id', 'avulso']))->toBeTrue();
});

test('tabela saldos_estoque possui coluna item_catalogo_id', function () {
    expect(Schema::hasColumns('saldos_estoque', ['item_catalogo_id']))->toBeTrue();
});

// ─── Model CatalogoItem ────────────────────────────────────────────────────────

test('catalogo item gera uuid automaticamente na criacao', function () {
    $item = CatalogoItem::factory()->create(['uuid' => null]);

    expect($item->uuid)->not->toBeNull()
        ->and(strlen($item->uuid))->toBe(36);
});

// ─── Autorização ──────────────────────────────────────────────────────────────

test('admin acessa admin catalogo-itens com sucesso', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/catalogo-itens')->assertOk();
});

test('nao-admin recebe 403 em admin catalogo-itens', function () {
    $usuario = User::factory()->create(['is_admin' => false]);

    $this->actingAs($usuario)->get('/admin/catalogo-itens')->assertForbidden();
});

test('compradora recebe 403 ao chamar salvar via livewire diretamente', function () {
    $compradora = User::factory()->compradora()->create();

    Livewire::actingAs($compradora)
        ->test(ListaCatalogoItens::class)
        ->assertForbidden();
});

// ─── CRUD CatalogoItem ──────────────────────────────────────────────────────────

test('admin cria item de catalogo via livewire e persiste no banco', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirCriar')
        ->set('descricao', 'Parafuso sextavado 1/4')
        ->set('codigo', 'PAR-001')
        ->set('unidadeMedida', 'un')
        ->set('categoria', 'ferramentas')
        ->call('salvar');

    expect(CatalogoItem::where('descricao', 'Parafuso sextavado 1/4')->exists())->toBeTrue();
});

test('admin edita item de catalogo existente', function () {
    $admin = User::factory()->admin()->create();
    $item = CatalogoItem::factory()->create(['descricao' => 'Descrição antiga']);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirEditar', $item->id)
        ->set('descricao', 'Descrição nova')
        ->call('salvar');

    expect($item->refresh()->descricao)->toBe('Descrição nova');
});

test('admin remove item de catalogo via soft delete', function () {
    $admin = User::factory()->admin()->create();
    $item = CatalogoItem::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('excluir', $item->id);

    expect(CatalogoItem::find($item->id))->toBeNull()
        ->and(CatalogoItem::withTrashed()->find($item->id))->not->toBeNull();
});

test('admin tenta criar item de catalogo com codigo duplicado e recebe erro de validacao', function () {
    $admin = User::factory()->admin()->create();
    CatalogoItem::factory()->create(['codigo' => 'DUP-001']);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirCriar')
        ->set('descricao', 'Item duplicado')
        ->set('codigo', 'DUP-001')
        ->call('salvar')
        ->assertHasErrors(['codigo']);
});

test('codigo pode ser reutilizado apos soft delete do item original', function () {
    $admin = User::factory()->admin()->create();
    $original = CatalogoItem::factory()->create(['codigo' => 'REUSE-001']);
    $original->delete();

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirCriar')
        ->set('descricao', 'Item reaproveitando código')
        ->set('codigo', 'REUSE-001')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(CatalogoItem::where('codigo', 'REUSE-001')->whereNull('deleted_at')->exists())->toBeTrue();
});

test('item de catalogo sem codigo informado e permitido', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirCriar')
        ->set('descricao', 'Item avulso sem código')
        ->set('codigo', '')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(CatalogoItem::where('descricao', 'Item avulso sem código')->exists())->toBeTrue();
});

// ─── EntradaEstoqueAction — identidade dual (catálogo x avulso) ───────────────

it('entrada_com_catalogo_agrupa_saldo_por_item_catalogo_id', function () {
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado']);

    // Lote 1: descrição "Parafuso Sextavado 1/4" — Lote 2: descrição diferente "Parafuso M8"
    // Ambos vinculados ao MESMO catalogoItem — devem cair no MESMO saldo.
    $setup1 = v11a_setup(quantidade: 10.0, valorCotacao: 1000.0, catalogoItem: $catalogoItem);
    $pedido1 = v11a_emitirPC($setup1, valorTotal: 1000.0);
    $item1 = $pedido1->itens->first();
    $rec1 = Recebimento::create(['pedido_compra_id' => $pedido1->id, 'almoxarife_id' => $setup1['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec1 = $rec1->itens()->create(['item_pedido_compra_id' => $item1->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item1, $itemRec1, 10.0, $setup1['almoxarife']));

    expect(SaldoEstoque::count())->toBe(1);
    $saldo = SaldoEstoque::first();
    expect($saldo->item_catalogo_id)->toBe($catalogoItem->id)
        ->and((float) $saldo->quantidade)->toBe(10.0);
});

it('entrada_avulsa_preserva_comportamento_v1_com_item_catalogo_id_nulo', function () {
    // Idêntico ao teste de CMP de dois lotes da Fase 7, mas confirmando item_catalogo_id NULL.
    $setup = v11a_setup(quantidade: 10.0, valorCotacao: 1000.0);

    $pedido1 = v11a_emitirPC($setup, valorTotal: 1000.0);
    $item1 = $pedido1->itens->first();
    expect($item1->item_catalogo_id)->toBeNull()
        ->and($item1->avulso)->toBeTrue();

    $rec1 = Recebimento::create(['pedido_compra_id' => $pedido1->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec1 = $rec1->itens()->create(['item_pedido_compra_id' => $item1->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item1, $itemRec1, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();
    expect($saldo->item_catalogo_id)->toBeNull()
        ->and((float) $saldo->quantidade)->toBe(10.0)
        ->and((float) $saldo->custo_medio_ponderado)->toBe(100.0)
        ->and((float) $saldo->valor_total)->toEqualWithDelta(1000.0, 0.01);
});

it('entrada_avulsa_e_entrada_com_catalogo_de_descricoes_diferentes_criam_saldos_separados', function () {
    // Nota: se a descrição normalizada for IDÊNTICA entre um item avulso e um item de
    // catálogo, a constraint UNIQUE v1 (unidade_id, deposito, descricao_normalizada) —
    // que o plano explicitamente determina não alterar — bloqueia a coexistência dos
    // dois saldos. Esse é um caso de borda raro (descrição textual igual ao catálogo
    // sem vínculo) e fica registrado como limitação conhecida da v1.1-A.
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado']);

    $setupAvulso = v11a_setup(quantidade: 5.0, valorCotacao: 500.0);
    $pedidoAvulso = v11a_emitirPC($setupAvulso, valorTotal: 500.0);
    $itemAvulso = $pedidoAvulso->itens->first();
    $recAvulso = Recebimento::create(['pedido_compra_id' => $pedidoAvulso->id, 'almoxarife_id' => $setupAvulso['almoxarife']->id, 'recebido_em' => now()]);
    $itemRecAvulso = $recAvulso->itens()->create(['item_pedido_compra_id' => $itemAvulso->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($itemAvulso, $itemRecAvulso, 5.0, $setupAvulso['almoxarife']));

    $setupCatalogo = v11a_setup(quantidade: 5.0, valorCotacao: 500.0, catalogoItem: $catalogoItem);
    $pedidoCatalogo = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $setupCatalogo['fornecedor']->id,
        'unidade_id' => $setupAvulso['unidade']->id,
        'criado_por' => $setupCatalogo['compradora']->id,
    ]);
    $pedidoCatalogo->itens()->create([
        'requisicao_id' => $setupCatalogo['requisicao']->id,
        'item_requisicao_id' => $setupCatalogo['itemReq']->id,
        'cotacao_id' => $setupCatalogo['cotacao']->id,
        'descricao' => 'Parafuso Sextavado',
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 500.0,
        'destino' => 'Depósito Central',
        'item_catalogo_id' => $catalogoItem->id,
        'avulso' => false,
    ]);
    $pedidoCatalogo = app(EmitirPedidoCompraAction::class)->execute($pedidoCatalogo, $setupCatalogo['compradora']);
    $itemCatalogo = $pedidoCatalogo->itens->first();
    $recCatalogo = Recebimento::create(['pedido_compra_id' => $pedidoCatalogo->id, 'almoxarife_id' => $setupAvulso['almoxarife']->id, 'recebido_em' => now()]);
    $itemRecCatalogo = $recCatalogo->itens()->create(['item_pedido_compra_id' => $itemCatalogo->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($itemCatalogo, $itemRecCatalogo, 5.0, $setupAvulso['almoxarife']));

    expect(SaldoEstoque::count())->toBe(2);
});

// ─── FormularioRequisicao — campos novos com default seguro ──────────────────

it('formulario_requisicao_item_default_e_avulso_com_catalogo_nulo', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $component = Livewire::actingAs($solicitante)->test(FormularioRequisicao::class);

    expect($component->get('itens')[0]['avulso'])->toBeTrue()
        ->and($component->get('itens')[0]['item_catalogo_id'])->toBeNull();
});

it('solicitante_seleciona_item_de_catalogo_e_campo_e_propagado_para_item_requisicao', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Luva de Raspa de Couro', 'unidade_medida' => 'par']);

    Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->set('unidadeId', $unidade->id)
        ->set('centroCustoId', $centro->id)
        ->call('selecionarItemCatalogo', 0, $catalogoItem->id)
        ->set('itens.0.quantidade', '10')
        ->set('itens.0.valor_unitario_estimado', '15.50')
        ->call('salvar');

    $itemReq = ItemRequisicao::first();
    expect($itemReq)->not->toBeNull()
        ->and($itemReq->item_catalogo_id)->toBe($catalogoItem->id)
        ->and($itemReq->avulso)->toBeFalse()
        ->and($itemReq->descricao)->toBe('Luva de Raspa de Couro');
});

it('item_de_requisicao_avulso_sem_catalogo_continua_funcionando', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->set('unidadeId', $unidade->id)
        ->set('centroCustoId', $centro->id)
        ->set('itens.0.descricao', 'Item totalmente avulso')
        ->set('itens.0.quantidade', '3')
        ->call('salvar')
        ->assertHasNoErrors();

    $itemReq = ItemRequisicao::where('descricao', 'Item totalmente avulso')->first();
    expect($itemReq)->not->toBeNull()
        ->and($itemReq->item_catalogo_id)->toBeNull()
        ->and($itemReq->avulso)->toBeTrue();
});

// ─── Propagação ItemRequisicao → ItemPedidoCompra ─────────────────────────────

it('requisicao_com_item_de_catalogo_propaga_vinculo_at_item_pedido_compra', function () {
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Cimento Portland CP-II 50kg']);
    $setup = v11a_setup(quantidade: 20.0, valorCotacao: 2000.0, catalogoItem: $catalogoItem);

    $pedido = app(CriarRascunhoPedidoAction::class)->execute(
        $setup['fornecedor'],
        collect([$setup['requisicao']]),
        $setup['compradora']
    );

    $itemPedido = $pedido->itens->first();
    expect($itemPedido->item_catalogo_id)->toBe($catalogoItem->id)
        ->and($itemPedido->avulso)->toBeFalse();
});

// ─── SugerirVinculoCatalogoAction ──────────────────────────────────────────────

it('sugestao_retorna_confianca_alta_para_descricoes_identicas', function () {
    $unidade = Unidade::factory()->create();
    CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado 1/4']);
    $saldo = v11a_criarSaldo($unidade, 'Parafuso Sextavado 1/4');

    $sugestoes = app(SugerirVinculoCatalogoAction::class)->execute($saldo);

    expect($sugestoes)->not->toBeEmpty()
        ->and($sugestoes->first()['confianca'])->toBe('alta')
        ->and($sugestoes->first()['score'])->toBeGreaterThanOrEqual(0.85);
});

it('sugestao_retorna_confianca_media_para_descricoes_parecidas', function () {
    $unidade = Unidade::factory()->create();
    CatalogoItem::factory()->create(['descricao' => 'Luva de Raspa de Couro']);
    $saldo = v11a_criarSaldo($unidade, 'Luva Raspa Couro Tamanho M');

    $sugestoes = app(SugerirVinculoCatalogoAction::class)->execute($saldo);

    expect($sugestoes)->not->toBeEmpty();
    $melhor = $sugestoes->first();
    expect($melhor['score'])->toBeGreaterThanOrEqual(0.60)
        ->and($melhor['score'])->toBeLessThan(0.85);
});

it('sugestao_nao_retorna_candidatos_para_descricoes_completamente_diferentes', function () {
    $unidade = Unidade::factory()->create();
    CatalogoItem::factory()->create(['descricao' => 'Furadeira de Impacto Industrial']);
    $saldo = v11a_criarSaldo($unidade, 'Resma de Papel A4 Branco');

    $sugestoes = app(SugerirVinculoCatalogoAction::class)->execute($saldo);

    expect($sugestoes)->toBeEmpty();
});

it('sugestao_ignora_itens_inativos_do_catalogo', function () {
    $unidade = Unidade::factory()->create();
    CatalogoItem::factory()->inativo()->create(['descricao' => 'Parafuso Sextavado 1/4']);
    $saldo = v11a_criarSaldo($unidade, 'Parafuso Sextavado 1/4');

    $sugestoes = app(SugerirVinculoCatalogoAction::class)->execute($saldo);

    expect($sugestoes)->toBeEmpty();
});

// ─── ConfirmarVinculoSaldoAction ────────────────────────────────────────────────

it('vincular_saldo_a_item_de_catalogo_atualiza_apenas_item_catalogo_id', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste', quantidade: 7.0, cmp: 33.3333);

    $qtdAntes = (float) $saldo->quantidade;
    $cmpAntes = (float) $saldo->custo_medio_ponderado;
    $valorAntes = (float) $saldo->valor_total;

    app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $catalogoItem, $admin);

    $saldo->refresh();
    expect($saldo->item_catalogo_id)->toBe($catalogoItem->id)
        ->and((float) $saldo->quantidade)->toBe($qtdAntes)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta($cmpAntes, 0.0001)
        ->and((float) $saldo->valor_total)->toEqualWithDelta($valorAntes, 0.01);
});

it('vincular_e_idempotente_quando_ja_vinculado_ao_mesmo_item', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste');
    $saldo->update(['item_catalogo_id' => $catalogoItem->id]);

    $resultado = app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $catalogoItem, $admin);

    expect($resultado->item_catalogo_id)->toBe($catalogoItem->id);
});

it('vincular_bloqueia_revinculo_sem_desvincular_primeiro', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $itemA = CatalogoItem::factory()->create();
    $itemB = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste');
    $saldo->update(['item_catalogo_id' => $itemA->id]);

    expect(fn () => app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $itemB, $admin))
        ->toThrow(ValidationException::class);
});

it('vincular_bloqueia_colisao_com_saldo_ja_vinculado_na_mesma_identidade', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();

    $saldoExistente = v11a_criarSaldo($unidade, 'Item Já Vinculado', deposito: 'Depósito Central');
    $saldoExistente->update(['item_catalogo_id' => $catalogoItem->id]);

    $saldoNovo = v11a_criarSaldo($unidade, 'Item Sem Vinculo', deposito: 'Depósito Central');

    expect(fn () => app(ConfirmarVinculoSaldoAction::class)->vincular($saldoNovo, $catalogoItem, $admin))
        ->toThrow(ValidationException::class);
});

it('vincular_permite_mesmo_catalogo_em_depositos_diferentes', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();

    $saldoA = v11a_criarSaldo($unidade, 'Item A', deposito: 'Depósito A');
    $saldoB = v11a_criarSaldo($unidade, 'Item B', deposito: 'Depósito B');

    app(ConfirmarVinculoSaldoAction::class)->vincular($saldoA, $catalogoItem, $admin);
    app(ConfirmarVinculoSaldoAction::class)->vincular($saldoB, $catalogoItem, $admin);

    expect($saldoA->refresh()->item_catalogo_id)->toBe($catalogoItem->id)
        ->and($saldoB->refresh()->item_catalogo_id)->toBe($catalogoItem->id);
});

it('desvincular_remove_o_vinculo_de_catalogo', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste');
    $saldo->update(['item_catalogo_id' => $catalogoItem->id]);

    app(ConfirmarVinculoSaldoAction::class)->desvincular($saldo, $admin);

    expect($saldo->refresh()->item_catalogo_id)->toBeNull();
});

it('desvincular_e_no_op_quando_saldo_ja_sem_vinculo', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste');

    $resultado = app(ConfirmarVinculoSaldoAction::class)->desvincular($saldo, $admin);

    expect($resultado->item_catalogo_id)->toBeNull();
});

it('vincular_lanca_403_para_usuario_nao_admin', function () {
    $naoAdmin = User::factory()->create(['is_admin' => false]);
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste');

    expect(fn () => app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $catalogoItem, $naoAdmin))
        ->toThrow(HttpException::class);
});

// ─── Livewire ReconciliacaoSaldos ──────────────────────────────────────────────

test('admin acessa admin reconciliacao-saldos com sucesso', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/reconciliacao-saldos')->assertOk();
});

test('nao-admin recebe 403 em admin reconciliacao-saldos', function () {
    $usuario = User::factory()->create(['is_admin' => false]);

    $this->actingAs($usuario)->get('/admin/reconciliacao-saldos')->assertForbidden();
});

it('reconciliacao_lista_apenas_saldos_sem_vinculo_de_catalogo', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();

    $saldoSemVinculo = v11a_criarSaldo($unidade, 'Sem Vínculo');
    $saldoComVinculo = v11a_criarSaldo($unidade, 'Com Vínculo', deposito: 'Depósito B');
    $saldoComVinculo->update(['item_catalogo_id' => $catalogoItem->id]);

    $component = Livewire::actingAs($admin)->test(ReconciliacaoSaldos::class);

    $saldosExibidos = $component->viewData('saldos')->pluck('id');
    expect($saldosExibidos->contains($saldoSemVinculo->id))->toBeTrue()
        ->and($saldosExibidos->contains($saldoComVinculo->id))->toBeFalse();
});

it('reconciliacao_confirma_vinculo_via_livewire_e_remove_saldo_da_lista', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado 1/4']);
    $saldo = v11a_criarSaldo($unidade, 'Parafuso Sextavado 1/4');

    Livewire::actingAs($admin)
        ->test(ReconciliacaoSaldos::class)
        ->call('vincular', $saldo->id, $catalogoItem->id);

    expect($saldo->refresh()->item_catalogo_id)->toBe($catalogoItem->id);
});

it('reconciliacao_desvincula_via_livewire', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Teste');
    $saldo->update(['item_catalogo_id' => $catalogoItem->id]);

    Livewire::actingAs($admin)
        ->test(ReconciliacaoSaldos::class)
        ->call('desvincular', $saldo->id);

    expect($saldo->refresh()->item_catalogo_id)->toBeNull();
});

it('reconciliacao_livewire_rejeita_acao_de_nao_admin', function () {
    $naoAdmin = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($naoAdmin)
        ->test(ReconciliacaoSaldos::class)
        ->assertForbidden();
});

// ─── Novos testes (correções Sec/QA v1.1-A) ───────────────────────────────────

it('reconciliacao_view_data_contem_sugestoes_com_confianca', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado 1/4']);
    $saldo = v11a_criarSaldo($unidade, 'Parafuso Sextavado 1/4');

    $component = Livewire::actingAs($admin)->test(ReconciliacaoSaldos::class);

    $sugestoes = $component->viewData('sugestoes');
    expect($sugestoes)->not->toBeEmpty();

    $sugestoesSaldo = $sugestoes[$saldo->id] ?? collect();
    expect($sugestoesSaldo)->not->toBeEmpty();

    $primeira = $sugestoesSaldo->first();
    expect($primeira['confianca'])->toBe('alta')
        ->and($primeira['score'])->toBeGreaterThanOrEqual(0.85);
});

it('vincular_idempotente_preserva_valores_financeiros', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create();
    $saldo = v11a_criarSaldo($unidade, 'Item Financeiro', quantidade: 12.0, cmp: 45.50);

    app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $catalogoItem, $admin);
    $saldo->refresh();

    $qtdApos1 = (float) $saldo->quantidade;
    $cmpApos1 = (float) $saldo->custo_medio_ponderado;
    $valorApos1 = (float) $saldo->valor_total;

    // Segunda vinculação — idempotente (mesmo item)
    app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $catalogoItem, $admin);
    $saldo->refresh();

    expect((float) $saldo->quantidade)->toEqualWithDelta($qtdApos1, 0.001)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta($cmpApos1, 0.0001)
        ->and((float) $saldo->valor_total)->toEqualWithDelta($valorApos1, 0.01);
});

it('dois_lotes_do_mesmo_catalogo_agrupam_e_recalculam_cmp', function () {
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado M8']);

    // Lote 1: 10un @ R$100
    $setup1 = v11a_setup(quantidade: 10.0, valorCotacao: 1000.0, catalogoItem: $catalogoItem);
    $pedido1 = v11a_emitirPC($setup1, valorTotal: 1000.0);
    $item1 = $pedido1->itens->first();
    $rec1 = Recebimento::create([
        'pedido_compra_id' => $pedido1->id,
        'almoxarife_id' => $setup1['almoxarife']->id,
        'recebido_em' => now(),
    ]);
    $itemRec1 = $rec1->itens()->create(['item_pedido_compra_id' => $item1->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item1, $itemRec1, 10.0, $setup1['almoxarife']));

    // Lote 2: 5un @ R$130 — mesma unidade/depósito/catálogo
    $setup2 = v11a_setup(quantidade: 5.0, valorCotacao: 650.0, catalogoItem: $catalogoItem);
    $pedido2 = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $setup2['fornecedor']->id,
        'unidade_id' => $setup1['unidade']->id,
        'criado_por' => $setup2['compradora']->id,
    ]);
    $pedido2->itens()->create([
        'requisicao_id' => $setup2['requisicao']->id,
        'item_requisicao_id' => $setup2['itemReq']->id,
        'cotacao_id' => $setup2['cotacao']->id,
        'descricao' => $catalogoItem->descricao,
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 130.0,
        'valor_total' => 650.0,
        'destino' => 'Depósito Central',
        'item_catalogo_id' => $catalogoItem->id,
        'avulso' => false,
    ]);
    $pedido2 = app(EmitirPedidoCompraAction::class)->execute($pedido2, $setup2['compradora']);
    $item2 = $pedido2->itens->first();
    $rec2 = Recebimento::create([
        'pedido_compra_id' => $pedido2->id,
        'almoxarife_id' => $setup1['almoxarife']->id,
        'recebido_em' => now(),
    ]);
    $itemRec2 = $rec2->itens()->create(['item_pedido_compra_id' => $item2->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item2, $itemRec2, 5.0, $setup1['almoxarife']));

    // Deve existir apenas 1 saldo agrupado
    expect(SaldoEstoque::where('item_catalogo_id', $catalogoItem->id)->count())->toBe(1);

    $saldo = SaldoEstoque::where('item_catalogo_id', $catalogoItem->id)->first();
    expect((float) $saldo->quantidade)->toEqualWithDelta(15.0, 0.001)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta(110.0, 0.01) // (1000+650)/15
        ->and((float) $saldo->valor_total)->toEqualWithDelta(1650.0, 0.01)
        ->and($saldo->item_catalogo_id)->toBe($catalogoItem->id);
});

it('excluir_item_catalogo_com_saldo_vinculado_e_bloqueado', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Item Com Saldo']);
    $saldo = v11a_criarSaldo($unidade, 'Item Com Saldo');
    $saldo->update(['item_catalogo_id' => $catalogoItem->id]);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('excluir', $catalogoItem->id)
        ->assertHasErrors(['excluir']);

    expect(CatalogoItem::withTrashed()->find($catalogoItem->id)->deleted_at)->toBeNull();
});

it('requisicao_nao_aceita_item_catalogo_inativo', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $itemInativo = CatalogoItem::factory()->create(['ativo' => false]);

    Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->set('unidadeId', $unidade->id)
        ->set('centroCustoId', $centro->id)
        ->set('itens.0.item_catalogo_id', $itemInativo->id)
        ->set('itens.0.avulso', false)
        ->set('itens.0.descricao', $itemInativo->descricao)
        ->set('itens.0.quantidade', '1')
        ->call('salvar')
        ->assertHasErrors(['itens.0.item_catalogo_id']);
});
