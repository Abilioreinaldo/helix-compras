<?php

use App\Actions\AjusteEstoqueAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Actions\EntradaEstoqueAction;
use App\Actions\RegistrarRecebimentoAction;
use App\Actions\SaidaEstoqueAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Enums\TipoMovimentacao;
use App\Models\Auditoria;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers ────────────────────────────────────────────────────────────────

function f7_setup(float $quantidade = 10.0, float $valorCotacao = 1000.0): array
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
        'descricao' => 'Produto de Teste F7',
        'quantidade' => $quantidade,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorCotacao / $quantidade,
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

function f7_emitirPC(array $setup, float $valorTotal = 1000.0, string $destino = 'Depósito Central'): PedidoCompra
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
    ]);

    return app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);
}

/**
 * Cria um segundo PC na mesma unidade que o setup original, com nova requisição/cotação.
 * Usado para testar múltiplas entradas no mesmo saldo.
 */
function f7_segundoPC(array $setup, float $quantidade, float $valorTotal, string $descricao = 'Produto de Teste F7', string $destino = 'Depósito Central'): PedidoCompra
{
    $centro = CentroCusto::factory()->create(['unidade_id' => $setup['unidade']->id]);

    $requisicao2 = Requisicao::create([
        'solicitante_id' => $setup['solicitante']->id,
        'unidade_id' => $setup['unidade']->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Aprovada,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHours(3),
        'aprovada_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    $itemReq2 = ItemRequisicao::create([
        'requisicao_id' => $requisicao2->id,
        'descricao' => $descricao,
        'quantidade' => $quantidade,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorTotal / $quantidade,
    ]);

    $cotacao2 = Cotacao::create([
        'requisicao_id' => $requisicao2->id,
        'fornecedor_id' => $setup['fornecedor']->id,
        'valor' => $valorTotal,
        'vencedora' => true,
        'criada_por' => $setup['compradora']->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    $pedido2 = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $setup['fornecedor']->id,
        'unidade_id' => $setup['unidade']->id,
        'criado_por' => $setup['compradora']->id,
    ]);

    $pedido2->itens()->create([
        'requisicao_id' => $requisicao2->id,
        'item_requisicao_id' => $itemReq2->id,
        'cotacao_id' => $cotacao2->id,
        'descricao' => $descricao,
        'quantidade' => $quantidade,
        'unidade_medida' => 'un',
        'valor_unitario' => $valorTotal / $quantidade,
        'valor_total' => $valorTotal,
        'destino' => $destino,
    ]);

    return app(EmitirPedidoCompraAction::class)->execute($pedido2, $setup['compradora']);
}

// ─── EntradaEstoqueAction ────────────────────────────────────────────────────

it('entrada_cria_saldo_e_define_cmp_inicial', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $recebimento = Recebimento::create([
        'pedido_compra_id' => $pedido->id,
        'almoxarife_id' => $setup['almoxarife']->id,
        'recebido_em' => now(),
    ]);
    $itemRec = $recebimento->itens()->create([
        'item_pedido_compra_id' => $item->id,
        'quantidade_recebida' => 10.0,
    ]);

    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();
    expect($saldo)->not->toBeNull()
        ->and((float) $saldo->quantidade)->toBe(10.0)
        ->and((float) $saldo->custo_medio_ponderado)->toBe(100.0)   // 1000/10
        ->and((float) $saldo->valor_total)->toEqualWithDelta(1000.0, 0.01);
});

it('multiplas_entradas_recalculam_cmp_corretamente', function () {
    // Lote 1: 10 un × R$100 = R$1.000 → CMP = 100
    // Lote 2:  5 un × R$120 = R$  600 → CMP = (1000+600)/(10+5) = 106,667
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);

    $pedido1 = f7_emitirPC($setup, valorTotal: 1000.0);
    $item1 = $pedido1->itens->first();
    $rec1 = Recebimento::create(['pedido_compra_id' => $pedido1->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec1 = $rec1->itens()->create(['item_pedido_compra_id' => $item1->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item1, $itemRec1, 10.0, $setup['almoxarife']));

    // Segundo PC: mesma unidade, mesma descrição e depósito, custo unitário diferente (R$120)
    $pedido2 = f7_segundoPC($setup, quantidade: 5.0, valorTotal: 600.0);
    $item2 = $pedido2->itens->first();
    $rec2 = Recebimento::create(['pedido_compra_id' => $pedido2->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec2 = $rec2->itens()->create(['item_pedido_compra_id' => $item2->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item2, $itemRec2, 5.0, $setup['almoxarife']));

    expect(SaldoEstoque::count())->toBe(1);
    $saldo = SaldoEstoque::first();
    expect((float) $saldo->quantidade)->toBe(15.0)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta(106.6667, 0.001)
        ->and((float) $saldo->valor_total)->toEqualWithDelta(1600.0, 0.01);
});

// ─── SaidaEstoqueAction ──────────────────────────────────────────────────────

it('saida_baixa_quantidade_pelo_cmp_sem_alterar_cmp', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();
    $cmpAntes = (float) $saldo->custo_medio_ponderado;

    app(SaidaEstoqueAction::class)->execute($saldo, 3.0, 'Consumo interno', $setup['almoxarife']);

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe(7.0)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta($cmpAntes, 0.0001)
        ->and((float) $saldo->valor_total)->toEqualWithDelta(7.0 * $cmpAntes, 0.01);
});

it('saida_rejeita_quantidade_maior_que_saldo', function () {
    $setup = f7_setup(quantidade: 5.0, valorCotacao: 500.0);
    $pedido = f7_emitirPC($setup, valorTotal: 500.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 5.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();

    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 10.0, 'Excesso', $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

// ─── AjusteEstoqueAction ─────────────────────────────────────────────────────

it('ajuste_positivo_aumenta_quantidade_pelo_cmp_vigente_sem_alterar_cmp', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();
    $cmpAntes = (float) $saldo->custo_medio_ponderado;

    app(AjusteEstoqueAction::class)->execute($saldo, TipoMovimentacao::AjustePositivo, 2.0, 'Inventário', $setup['almoxarife']);

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe(12.0)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta($cmpAntes, 0.0001)
        ->and((float) $saldo->valor_total)->toEqualWithDelta(12.0 * $cmpAntes, 0.01);
});

it('ajuste_negativo_reduz_quantidade_pelo_cmp_vigente_sem_alterar_cmp', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();
    $cmpAntes = (float) $saldo->custo_medio_ponderado;

    app(AjusteEstoqueAction::class)->execute($saldo, TipoMovimentacao::AjusteNegativo, 3.0, 'Avaria', $setup['almoxarife']);

    $saldo->refresh();
    expect((float) $saldo->quantidade)->toBe(7.0)
        ->and((float) $saldo->custo_medio_ponderado)->toEqualWithDelta($cmpAntes, 0.0001)
        ->and((float) $saldo->valor_total)->toEqualWithDelta(7.0 * $cmpAntes, 0.01);
});

it('ajuste_negativo_rejeita_quantidade_maior_que_saldo', function () {
    $setup = f7_setup(quantidade: 5.0, valorCotacao: 500.0);
    $pedido = f7_emitirPC($setup, valorTotal: 500.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 5.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();

    expect(fn () => app(AjusteEstoqueAction::class)->execute($saldo, TipoMovimentacao::AjusteNegativo, 10.0, 'Erro', $setup['almoxarife']))
        ->toThrow(ValidationException::class);
});

// ─── Concorrência ────────────────────────────────────────────────────────────

it('concorrencia_duas_entradas_sequenciais_nao_corrompem_saldo', function () {
    // Em SQLite, transações são serializadas — o teste valida a lógica de acumulação,
    // não a corrida de processos simultâneos (o que exigiria MySQL + threads reais).
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();

    $rec1 = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec1 = $rec1->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 4.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec1, 4.0, $setup['almoxarife']));

    $rec2 = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec2 = $rec2->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 6.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec2, 6.0, $setup['almoxarife']));

    expect(SaldoEstoque::count())->toBe(1);
    $saldo = SaldoEstoque::first();
    expect((float) $saldo->quantidade)->toBe(10.0)
        ->and($saldo->movimentacoes()->count())->toBe(2);
});

// ─── Integração Recebimento → Estoque ────────────────────────────────────────

it('recebimento_cria_entrada_de_estoque_automaticamente', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();

    expect(SaldoEstoque::count())->toBe(0);

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 10.0]);

    expect(SaldoEstoque::count())->toBe(1);
    $saldo = SaldoEstoque::first();
    expect((float) $saldo->quantidade)->toBe(10.0)
        ->and($saldo->deposito)->toBe('Depósito Central')
        ->and($saldo->movimentacoes()->count())->toBe(1)
        ->and($saldo->movimentacoes()->first()->tipo)->toBe(TipoMovimentacao::Entrada);
});

it('recebimento_parcial_acumula_saldo_em_entradas_separadas', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 4.0]);
    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 6.0]);

    expect(SaldoEstoque::count())->toBe(1);
    $saldo = SaldoEstoque::first();
    expect((float) $saldo->quantidade)->toBe(10.0)
        ->and($saldo->movimentacoes()->count())->toBe(2);
});

// ─── Guarda de Unidade (CA8) ─────────────────────────────────────────────────

it('saida_rejeita_almoxarife_de_outra_unidade', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();

    // Almoxarife de outra unidade não pode fazer saída neste saldo
    $outraUnidade = Unidade::factory()->create();
    $outroAlmoxarife = User::factory()->create();
    $outroAlmoxarife->unidades()->attach($outraUnidade->id, ['perfil' => Perfil::Almoxarife->value]);

    expect(fn () => app(SaidaEstoqueAction::class)->execute($saldo, 1.0, 'Tentativa', $outroAlmoxarife))
        ->toThrow(ValidationException::class);
});

it('ajuste_rejeita_almoxarife_de_outra_unidade', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    $saldo = SaldoEstoque::first();

    $outraUnidade = Unidade::factory()->create();
    $outroAlmoxarife = User::factory()->create();
    $outroAlmoxarife->unidades()->attach($outraUnidade->id, ['perfil' => Perfil::Almoxarife->value]);

    expect(fn () => app(AjusteEstoqueAction::class)->execute($saldo, TipoMovimentacao::AjusteNegativo, 1.0, 'Tentativa', $outroAlmoxarife))
        ->toThrow(ValidationException::class);
});

// ─── Auditoria ───────────────────────────────────────────────────────────────

it('movimentacao_de_estoque_gera_trilha_de_auditoria', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);
    $pedido = f7_emitirPC($setup, valorTotal: 1000.0);
    $item = $pedido->itens->first();
    $rec = Recebimento::create(['pedido_compra_id' => $pedido->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec = $rec->itens()->create(['item_pedido_compra_id' => $item->id, 'quantidade_recebida' => 10.0]);

    $auditoriaAntes = Auditoria::where('auditavel_type', 'App\Models\MovimentacaoEstoque')->count();

    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item, $itemRec, 10.0, $setup['almoxarife']));

    expect(Auditoria::where('auditavel_type', 'App\Models\MovimentacaoEstoque')->count())
        ->toBeGreaterThan($auditoriaAntes);
});

// ─── Edge Cases de Normalização e Depósitos ──────────────────────────────────

it('dois_depositos_diferentes_criam_saldos_separados', function () {
    $setup = f7_setup(quantidade: 10.0, valorCotacao: 1000.0);

    $pedido1 = f7_emitirPC($setup, valorTotal: 1000.0, destino: 'Depósito A');
    $item1 = $pedido1->itens->first();
    $rec1 = Recebimento::create(['pedido_compra_id' => $pedido1->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec1 = $rec1->itens()->create(['item_pedido_compra_id' => $item1->id, 'quantidade_recebida' => 10.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item1, $itemRec1, 10.0, $setup['almoxarife']));

    $pedido2 = f7_segundoPC($setup, quantidade: 5.0, valorTotal: 500.0, destino: 'Depósito B');
    $item2 = $pedido2->itens->first();
    $rec2 = Recebimento::create(['pedido_compra_id' => $pedido2->id, 'almoxarife_id' => $setup['almoxarife']->id, 'recebido_em' => now()]);
    $itemRec2 = $rec2->itens()->create(['item_pedido_compra_id' => $item2->id, 'quantidade_recebida' => 5.0]);
    DB::transaction(fn () => app(EntradaEstoqueAction::class)->execute($item2, $itemRec2, 5.0, $setup['almoxarife']));

    expect(SaldoEstoque::count())->toBe(2);
});

// ─── Rotas e Autorização ─────────────────────────────────────────────────────

it('rota_estoque_retorna_403_sem_perfil_almoxarife', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('almoxarife.estoque.index'))
        ->assertForbidden();
});

it('rota_estoque_retorna_403_para_solicitante', function () {
    $setup = f7_setup();

    $this->actingAs($setup['solicitante'])
        ->get(route('almoxarife.estoque.index'))
        ->assertForbidden();
});

it('rota_estoque_retorna_200_com_perfil_almoxarife', function () {
    $setup = f7_setup();

    $this->actingAs($setup['almoxarife'])
        ->get(route('almoxarife.estoque.index'))
        ->assertSuccessful();
});
