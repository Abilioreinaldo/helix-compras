<?php

use App\Actions\EmitirPedidoCompraAction;
use App\Actions\EntradaEstoqueAction;
use App\Actions\RegistrarRecebimentoAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRecebimento;
use App\Models\ItemRequisicao;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\PedidoCompra;
use App\Models\Recebimento;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers (espelham v11a_*, com CatalogoItem controla_lote e item de PC vinculado) ──

/**
 * Monta a cadeia requisição → cotação → PC com um item de catálogo (controla_lote opcional).
 *
 * @return array{unidade: Unidade, almoxarife: User, compradora: User, catalogo: CatalogoItem, item: ItemPedidoCompra, pedido: PedidoCompra}
 */
function el_setup(bool $controlaLote = true, float $quantidade = 10.0, float $valorTotal = 1000.0, string $destino = 'Depósito Central'): array
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

    $catalogo = CatalogoItem::factory()->create([
        'descricao' => 'Insumo Cervejaria EL',
        'controla_lote' => $controlaLote,
    ]);

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
        'descricao' => $catalogo->descricao,
        'quantidade' => $quantidade,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorTotal / $quantidade,
        'item_catalogo_id' => $catalogo->id,
        'avulso' => false,
    ]);

    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id,
        'fornecedor_id' => $fornecedor->id,
        'valor' => $valorTotal,
        'vencedora' => true,
        'criada_por' => $compradora->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
    ]);

    $pedido->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => $catalogo->descricao,
        'quantidade' => $quantidade,
        'unidade_medida' => 'un',
        'valor_unitario' => $valorTotal / $quantidade,
        'valor_total' => $valorTotal,
        'destino' => $destino,
        'item_catalogo_id' => $catalogo->id,
        'avulso' => false,
    ]);

    $pedido = app(EmitirPedidoCompraAction::class)->execute($pedido, $compradora);
    $item = $pedido->itens->first();

    return compact('unidade', 'almoxarife', 'compradora', 'catalogo', 'item', 'pedido');
}

/** Cria recebimento + item de recebimento para o item do PC. */
function el_receber(ItemPedidoCompra $item, User $almoxarife, float $quantidade): ItemRecebimento
{
    $pedidoId = $item->pedidoCompra()->withoutGlobalScopes()->value('id');

    $recebimento = Recebimento::create([
        'pedido_compra_id' => $pedidoId,
        'almoxarife_id' => $almoxarife->id,
        'recebido_em' => now(),
    ]);

    return $recebimento->itens()->create([
        'item_pedido_compra_id' => $item->id,
        'quantidade_recebida' => $quantidade,
    ]);
}

// ─── Entrada COM lote (comportamento novo) ────────────────────────────────────

it('entrada_com_lote_credita_lote_vinculado_e_mantem_invariante', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 10.0);

    $movimento = DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 10.0, $setup['almoxarife'], numeroLote: 'L-001', validade: '2027-01-31'
    ));

    $saldo = SaldoEstoque::first();
    $lote = LoteEstoque::first();

    expect(LoteEstoque::count())->toBe(1)
        ->and($lote->numero_lote)->toBe('L-001')
        ->and($lote->saldo_estoque_id)->toBe($saldo->id)
        ->and((float) $lote->quantidade)->toBe(10.0)
        ->and($lote->validade->format('Y-m-d'))->toBe('2027-01-31')
        ->and($lote->fundido_para_id)->toBeNull()
        // Invariante-mestra: SUM(lotes vivos) == saldo.quantidade
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe((float) $saldo->quantidade)
        // Movimentação rastreia o lote
        ->and($movimento->lote_estoque_id)->toBe($lote->id);
});

it('entrada_com_lote_sem_validade_cria_lote_com_validade_nula', function () {
    $setup = el_setup(controlaLote: true, quantidade: 4.0, valorTotal: 400.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 4.0);

    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 4.0, $setup['almoxarife'], numeroLote: 'SEM-VALIDADE', validade: null
    ));

    $lote = LoteEstoque::first();
    expect($lote->validade)->toBeNull()
        ->and((float) $lote->quantidade)->toBe(4.0);
});

it('segundo_recebimento_mesmo_lote_soma_no_lote_existente_sem_duplicar', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);

    $itemRec1 = el_receber($setup['item'], $setup['almoxarife'], 6.0);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec1, 6.0, $setup['almoxarife'], numeroLote: 'L-007', validade: '2027-06-30'
    ));

    $itemRec2 = el_receber($setup['item'], $setup['almoxarife'], 4.0);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec2, 4.0, $setup['almoxarife'], numeroLote: 'L-007', validade: '2027-06-30'
    ));

    $saldo = SaldoEstoque::first();
    $lote = LoteEstoque::first();

    expect(SaldoEstoque::count())->toBe(1)
        ->and(LoteEstoque::count())->toBe(1)               // somou, não duplicou
        ->and((float) $lote->quantidade)->toBe(10.0)
        ->and((float) $saldo->quantidade)->toBe(10.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe((float) $saldo->quantidade)
        // Ledger append-only: uma movimentação por recebimento, ambas no mesmo lote
        ->and(MovimentacaoEstoque::where('lote_estoque_id', $lote->id)->count())->toBe(2);
});

it('lotes_de_numeros_diferentes_no_mesmo_saldo_somam_para_o_saldo', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);

    $itemRecA = el_receber($setup['item'], $setup['almoxarife'], 7.0);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRecA, 7.0, $setup['almoxarife'], numeroLote: 'L-AAA', validade: '2027-03-01'
    ));

    $itemRecB = el_receber($setup['item'], $setup['almoxarife'], 3.0);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRecB, 3.0, $setup['almoxarife'], numeroLote: 'L-BBB', validade: '2027-09-01'
    ));

    $saldo = SaldoEstoque::first();

    expect(LoteEstoque::count())->toBe(2)
        ->and((float) $saldo->quantidade)->toBe(10.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe((float) $saldo->quantidade);
});

// ─── Adversário: controla_lote sem número de lote ─────────────────────────────

it('entrada_controla_lote_sem_numero_de_lote_bloqueia_e_nao_cria_saldo_orfao', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 10.0);

    // Baseline: um saldo pré-existente de OUTRA unidade. Se a action escrevesse antes de
    // validar, o count subiria de 1 para 2 — prova "não escreveu", não apenas "banco vazio".
    $outraUnidade = Unidade::factory()->create();
    SaldoEstoque::create([
        'unidade_id' => $outraUnidade->id,
        'deposito' => 'Outro',
        'descricao_item' => 'Pré-existente',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Pré-existente'),
        'unidade_medida' => 'un',
        'quantidade' => 5,
        'custo_medio_ponderado' => 1,
        'valor_total' => 5,
    ]);

    // Sem transação externa: se a action criasse saldo antes de validar, ele persistiria.
    expect(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 10.0, $setup['almoxarife'], numeroLote: null
    ))->toThrow(ValidationException::class);

    expect(SaldoEstoque::count())->toBe(1)   // apenas o pré-existente; nada novo escrito
        ->and(LoteEstoque::count())->toBe(0)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

it('entrada_controla_lote_com_numero_em_branco_tambem_bloqueia', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 10.0);

    expect(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 10.0, $setup['almoxarife'], numeroLote: '   '
    ))->toThrow(ValidationException::class);

    expect(SaldoEstoque::count())->toBe(0)
        ->and(LoteEstoque::count())->toBe(0);
});

// ─── Regressão: item SEM controle de lote mantém comportamento idêntico ───────

it('entrada_sem_controle_de_lote_nao_cria_lote_e_zera_lote_estoque_id', function () {
    $setup = el_setup(controlaLote: false, quantidade: 10.0, valorTotal: 1000.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 10.0);

    $movimento = DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 10.0, $setup['almoxarife']
    ));

    $saldo = SaldoEstoque::first();

    expect(LoteEstoque::count())->toBe(0)
        ->and($movimento->lote_estoque_id)->toBeNull()
        ->and((float) $saldo->quantidade)->toBe(10.0)
        ->and((float) $saldo->custo_medio_ponderado)->toBe(100.0);
});

it('parametros_de_lote_sao_ignorados_quando_item_nao_controla_lote', function () {
    $setup = el_setup(controlaLote: false, quantidade: 5.0, valorTotal: 500.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 5.0);

    $movimento = DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 5.0, $setup['almoxarife'], numeroLote: 'IGNORADO', validade: '2027-01-01'
    ));

    expect(LoteEstoque::count())->toBe(0)
        ->and($movimento->lote_estoque_id)->toBeNull();
});

// ─── CMP/valor: a mecânica de lote não altera o cálculo financeiro ────────────

it('cmp_e_valor_total_preservados_no_caminho_com_lote', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 10.0);

    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 10.0, $setup['almoxarife'], numeroLote: 'L-CMP', validade: '2027-01-01'
    ));

    $saldo = SaldoEstoque::first();
    // Idêntico ao caminho sem lote: 1000/10 = 100; o lote não toca CMP nem valor.
    expect((float) $saldo->custo_medio_ponderado)->toBe(100.0)
        ->and((float) $saldo->valor_total)->toEqualWithDelta(1000.0, 0.01);
});

// ─── Validade: borda de string vazia e preservação no 2º recebimento ──────────

it('validade_string_vazia_e_tratada_como_nula', function () {
    $setup = el_setup(controlaLote: true, quantidade: 3.0, valorTotal: 300.0);
    $itemRec = el_receber($setup['item'], $setup['almoxarife'], 3.0);

    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec, 3.0, $setup['almoxarife'], numeroLote: 'L-VAZIA', validade: ''
    ));

    expect(LoteEstoque::first()->validade)->toBeNull();
});

it('segundo_recebimento_com_validade_diferente_preserva_a_validade_original', function () {
    $setup = el_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);

    $itemRec1 = el_receber($setup['item'], $setup['almoxarife'], 5.0);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec1, 5.0, $setup['almoxarife'], numeroLote: 'L-VAL', validade: '2027-01-01'
    ));

    $itemRec2 = el_receber($setup['item'], $setup['almoxarife'], 5.0);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute(
        $setup['item'], $itemRec2, 5.0, $setup['almoxarife'], numeroLote: 'L-VAL', validade: '2099-12-31'
    ));

    $lote = LoteEstoque::first();
    expect(LoteEstoque::count())->toBe(1)
        ->and((float) $lote->quantidade)->toBe(10.0)
        ->and($lote->validade->format('Y-m-d'))->toBe('2027-01-01');   // primeira entrada manda
});

// ─── Integração: RegistrarRecebimentoAction repassa lote e é transacional ─────

it('registrar_recebimento_repassa_numero_de_lote_e_validade_para_a_entrada', function () {
    $setup = el_setup(controlaLote: true, quantidade: 8.0, valorTotal: 800.0);

    app(RegistrarRecebimentoAction::class)->execute(
        $setup['pedido'],
        $setup['almoxarife'],
        [$setup['item']->id => 8.0],
        null,
        [$setup['item']->id => ['numero_lote' => 'L-RR', 'validade' => '2027-12-31']],
    );

    $saldo = SaldoEstoque::first();
    $lote = LoteEstoque::first();
    expect(LoteEstoque::count())->toBe(1)
        ->and($lote->numero_lote)->toBe('L-RR')
        ->and($lote->validade->format('Y-m-d'))->toBe('2027-12-31')
        ->and((float) $lote->quantidade)->toBe(8.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe((float) $saldo->quantidade);
});

it('registrar_recebimento_de_item_controla_lote_sem_lote_falha_e_reverte_tudo', function () {
    $setup = el_setup(controlaLote: true, quantidade: 8.0, valorTotal: 800.0);

    // Sem o array de lotes → numeroLote chega null para item controla_lote → ValidationException.
    expect(fn () => app(RegistrarRecebimentoAction::class)->execute(
        $setup['pedido'],
        $setup['almoxarife'],
        [$setup['item']->id => 8.0],
    ))->toThrow(ValidationException::class);

    // Transação única do recebimento: o Recebimento e o ItemRecebimento criados antes da
    // entrada são revertidos junto — nada de saldo/lote/recebimento órfão.
    expect(Recebimento::count())->toBe(0)
        ->and(SaldoEstoque::count())->toBe(0)
        ->and(LoteEstoque::count())->toBe(0);
});
