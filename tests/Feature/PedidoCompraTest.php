<?php

use App\Actions\CancelarPedidoCompraAction;
use App\Actions\CriarRascunhoPedidoAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Mail\PedidoCompraEmitido;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Cria uma requisição Aprovada com cotação vencedora pronta para virar PC.
 *
 * @return array{unidade: Unidade, solicitante: User, compradora: User, fornecedor: Fornecedor,
 *               requisicao: Requisicao, itemReq: ItemRequisicao, cotacao: Cotacao}
 */
function setupParaPC(float $valorCotacao = 1000.0): array
{
    $unidade = Unidade::factory()->create();

    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $compradora = User::factory()->compradora()->create();
    $compradora->unidades()->attach($unidade->id, ['perfil' => Perfil::CompradoraSenior->value]);

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
        'descricao' => 'Produto de Teste',
        'quantidade' => 10,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorCotacao / 10,
    ]);

    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id,
        'fornecedor_id' => $fornecedor->id,
        'valor' => $valorCotacao,
        'vencedora' => true,
        'criada_por' => $compradora->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    return compact('unidade', 'solicitante', 'compradora', 'fornecedor', 'requisicao', 'itemReq', 'cotacao');
}

/**
 * Cria um rascunho de PC com item preenchido (valor e destino) pronto para emissão.
 */
function criarRascunhoPreenchido(array $setup, float $valorTotal = 1000.0): PedidoCompra
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
        'destino' => 'Unidade Central',
    ]);

    return $pedido;
}

// ─── CriarRascunhoPedidoAction ────────────────────────────────────────────

it('cria_rascunho_de_pc_a_partir_de_requisicao_aprovada', function () {
    $setup = setupParaPC();
    ['fornecedor' => $fornecedor, 'requisicao' => $requisicao, 'compradora' => $compradora] = $setup;

    $pedido = app(CriarRascunhoPedidoAction::class)->execute(
        $fornecedor,
        collect([$requisicao]),
        $compradora
    );

    expect($pedido)->toBeInstanceOf(PedidoCompra::class)
        ->and($pedido->status)->toBe(StatusPedidoCompra::Rascunho)
        ->and($pedido->fornecedor_id)->toBe($fornecedor->id)
        ->and($pedido->itens)->toHaveCount(1);
});

it('criar_rascunho_rejeita_lista_vazia', function () {
    $setup = setupParaPC();

    expect(fn () => app(CriarRascunhoPedidoAction::class)->execute(
        $setup['fornecedor'],
        collect(),
        $setup['compradora']
    ))->toThrow(ValidationException::class);
});

it('criar_rascunho_rejeita_requisicao_sem_status_aprovado', function () {
    $setup = setupParaPC();
    $setup['requisicao']->update(['status' => StatusRequisicao::EmTriagem]);

    expect(fn () => app(CriarRascunhoPedidoAction::class)->execute(
        $setup['fornecedor'],
        collect([$setup['requisicao']]),
        $setup['compradora']
    ))->toThrow(ValidationException::class);
});

it('criar_rascunho_rejeita_sem_cotacao_vencedora_do_fornecedor', function () {
    $setup = setupParaPC();
    $setup['cotacao']->update(['vencedora' => false]);

    expect(fn () => app(CriarRascunhoPedidoAction::class)->execute(
        $setup['fornecedor'],
        collect([$setup['requisicao']]),
        $setup['compradora']
    ))->toThrow(ValidationException::class);
});

// ─── EmitirPedidoCompraAction ─────────────────────────────────────────────

it('emitir_gera_numero_sequencial_PC_AAAA_NNNN', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);

    $emitido = app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);

    expect($emitido->numero)->toBe(sprintf('PC-%04d-0001', now()->year))
        ->and($emitido->status)->toBe(StatusPedidoCompra::Emitido)
        ->and($emitido->emitido_em)->not->toBeNull();
});

it('emitir_transiciona_requisicao_para_em_compra', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);

    app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);

    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::EmCompra);
});

it('emitir_envia_email_ao_solicitante', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);

    app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);

    Mail::assertSent(PedidoCompraEmitido::class);
});

it('emitir_rejeita_item_sem_valor_unitario', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);
    $pedido->itens()->update(['valor_unitario' => 0, 'valor_total' => 0]);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']))
        ->toThrow(ValidationException::class);
});

it('emitir_rejeita_item_sem_destino', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);
    $pedido->itens()->update(['destino' => null]);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']))
        ->toThrow(ValidationException::class);
});

it('emitir_rejeita_fornecedor_nao_homologado', function () {
    $setup = setupParaPC();
    $setup['fornecedor']->update(['homologado' => false]);
    $pedido = criarRascunhoPreenchido($setup);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']))
        ->toThrow(ValidationException::class);
});

it('emitir_rejeita_desmembramento_que_excede_cotacao', function () {
    $setup = setupParaPC(1000.0);
    [
        'fornecedor' => $fornecedor, 'unidade' => $unidade, 'compradora' => $compradora,
        'requisicao' => $requisicao, 'itemReq' => $itemReq, 'cotacao' => $cotacao,
    ] = $setup;

    // PC-A emitido cobrindo R$600 — simulado diretamente no banco
    $pcA = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'numero' => 'PC-9999-9001',
        'ano' => 9999,
        'sequencia' => 9001,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
        'emitido_em' => now()->subMinutes(30),
        'emitido_por' => $compradora->id,
    ]);
    $pcA->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Produto de Teste',
        'quantidade' => 6.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 600.0,
        'destino' => 'Central',
    ]);
    $requisicao->update(['status' => StatusRequisicao::EmCompra]);

    // PC-B tenta cobrir R$500 → total seria R$1100, excede teto de R$1000
    $pcB = criarRascunhoPreenchido($setup, 500.0);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($pcB, $compradora))
        ->toThrow(ValidationException::class);
});

// ─── CancelarPedidoCompraAction ────────────────────────────────────────────

it('cancelar_rascunho_sem_motivo', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);

    app(CancelarPedidoCompraAction::class)->execute($pedido, $setup['compradora'], '');

    expect($pedido->fresh()->status)->toBe(StatusPedidoCompra::Cancelado);
});

it('cancelar_emitido_exige_motivo', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);
    app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);

    expect(fn () => app(CancelarPedidoCompraAction::class)->execute($pedido, $setup['compradora'], ''))
        ->toThrow(ValidationException::class);
});

it('cancelar_emitido_reverte_requisicao_para_aprovada', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);
    app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);

    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::EmCompra);

    app(CancelarPedidoCompraAction::class)->execute($pedido, $setup['compradora'], 'Cancelado por teste.');

    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::Aprovada);
});

it('cancelar_pc_de_requisicao_desmembrada_mantem_em_compra', function () {
    $setup = setupParaPC(1000.0);
    [
        'fornecedor' => $fornecedor, 'unidade' => $unidade, 'compradora' => $compradora,
        'requisicao' => $requisicao, 'itemReq' => $itemReq, 'cotacao' => $cotacao,
    ] = $setup;

    // PC-A emitido: R$600 da requisição
    $pcA = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'numero' => 'PC-9999-9001',
        'ano' => 9999,
        'sequencia' => 9001,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
        'emitido_em' => now()->subMinutes(30),
        'emitido_por' => $compradora->id,
    ]);
    $pcA->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Produto de Teste',
        'quantidade' => 6.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 600.0,
        'destino' => 'Central',
    ]);

    // PC-B emitido: R$400 da mesma requisição (desmembramento)
    $pcB = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'numero' => 'PC-9999-9002',
        'ano' => 9999,
        'sequencia' => 9002,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
        'emitido_em' => now()->subMinutes(20),
        'emitido_por' => $compradora->id,
    ]);
    $pcB->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Produto de Teste',
        'quantidade' => 4.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 400.0,
        'destino' => 'Filial',
    ]);
    $requisicao->update(['status' => StatusRequisicao::EmCompra]);

    app(CancelarPedidoCompraAction::class)->execute($pcA, $compradora, 'Replanejamento.');

    // Req permanece EmCompra pois PC-B ainda está ativo
    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::EmCompra)
        ->and($pcA->fresh()->status)->toBe(StatusPedidoCompra::Cancelado);
});

it('cancelar_pc_desmembrado_devolve_saldo_para_novo_pc', function () {
    $setup = setupParaPC(1000.0);
    [
        'fornecedor' => $fornecedor, 'unidade' => $unidade, 'compradora' => $compradora,
        'requisicao' => $requisicao, 'itemReq' => $itemReq, 'cotacao' => $cotacao,
    ] = $setup;

    // PC-A emitido: R$600; PC-B emitido: R$400 — juntos esgotam o teto de R$1000
    $pcA = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'numero' => 'PC-9999-9001',
        'ano' => 9999,
        'sequencia' => 9001,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
        'emitido_em' => now()->subMinutes(30),
        'emitido_por' => $compradora->id,
    ]);
    $pcA->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Produto de Teste',
        'quantidade' => 6.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 600.0,
        'destino' => 'Central',
    ]);

    PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'numero' => 'PC-9999-9002',
        'ano' => 9999,
        'sequencia' => 9002,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
        'emitido_em' => now()->subMinutes(20),
        'emitido_por' => $compradora->id,
    ])->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Produto de Teste',
        'quantidade' => 4.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 400.0,
        'destino' => 'Filial',
    ]);
    $requisicao->update(['status' => StatusRequisicao::EmCompra]);

    // Cancelar PC-A: libera R$600 do teto
    app(CancelarPedidoCompraAction::class)->execute($pcA, $compradora, 'Cancelado para reemissão.');

    // PC-C cobre os R$600 liberados: PC-B(400) + PC-C(600) = 1000 = teto → deve emitir
    $pcC = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $compradora->id,
    ]);
    $pcC->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Produto de Teste',
        'quantidade' => 6.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 100.0,
        'valor_total' => 600.0,
        'destino' => 'Unidade Norte',
    ]);

    $pcCEmitido = app(EmitirPedidoCompraAction::class)->execute($pcC, $compradora);

    expect($pcCEmitido->status)->toBe(StatusPedidoCompra::Emitido)
        ->and($pcCEmitido->numero)->not->toBeNull();
});

// ─── Rotas ─────────────────────────────────────────────────────────────────

it('rota_pdf_retorna_200_para_pc_emitido', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);
    $emitido = app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);

    $this->actingAs($setup['compradora'])
        ->get(route('compradora.pedidos.pdf', $emitido->id))
        ->assertSuccessful();
});

it('rota_pdf_retorna_404_para_rascunho', function () {
    $setup = setupParaPC();
    $pedido = criarRascunhoPreenchido($setup);

    $this->actingAs($setup['compradora'])
        ->get(route('compradora.pedidos.pdf', $pedido->id))
        ->assertNotFound();
});

it('rota_pedidos_retorna_403_sem_perfil_compradora', function () {
    $setup = setupParaPC();

    $this->actingAs($setup['solicitante'])
        ->get(route('compradora.pedidos.index'))
        ->assertForbidden();
});
