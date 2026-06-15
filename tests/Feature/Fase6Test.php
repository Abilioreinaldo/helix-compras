<?php

use App\Actions\EmitirPedidoCompraAction;
use App\Actions\RegistrarRecebimentoAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRecebimentoPedido;
use App\Enums\StatusRequisicao;
use App\Mail\PedidoCompraRecebido;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\Recebimento;
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
 * Cria setup base com almoxarife para testes de F6.
 *
 * @return array{unidade: Unidade, solicitante: User, almoxarife: User, compradora: User,
 *               fornecedor: Fornecedor, requisicao: Requisicao, itemReq: ItemRequisicao, cotacao: Cotacao}
 */
function f6_setup(float $quantidade = 10.0, float $valorCotacao = 1000.0): array
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
        'descricao' => 'Produto de Teste F6',
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

/**
 * Emite um PC com item preenchido a partir do setup f6.
 */
function f6_emitirPC(array $setup, float $valorTotal = 1000.0): PedidoCompra
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
        'destino' => 'Depósito Central',
    ]);

    return app(EmitirPedidoCompraAction::class)->execute($pedido, $setup['compradora']);
}

// ─── statusRecebimento() ─────────────────────────────────────────────────────

it('statusRecebimento_retorna_Pendente_para_pc_sem_recebimento', function () {
    $setup = f6_setup();
    $pedido = f6_emitirPC($setup);

    expect($pedido->statusRecebimento())->toBe(StatusRecebimentoPedido::Pendente);
});

it('statusRecebimento_retorna_Parcial_apos_recebimento_parcial', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 5.0]);

    expect($pedido->fresh()->statusRecebimento())->toBe(StatusRecebimentoPedido::Parcial);
});

it('statusRecebimento_retorna_Total_apos_recebimento_completo', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 10.0]);

    expect($pedido->fresh()->statusRecebimento())->toBe(StatusRecebimentoPedido::Total);
});

// ─── RegistrarRecebimentoAction ──────────────────────────────────────────────

it('registra_recebimento_parcial_e_cria_registros_corretos', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    $recebimento = app(RegistrarRecebimentoAction::class)->execute(
        $pedido, $setup['almoxarife'], [$item->id => 4.0], 'Entrega parcial'
    );

    expect($recebimento)->toBeInstanceOf(Recebimento::class)
        ->and($recebimento->almoxarife_id)->toBe($setup['almoxarife']->id)
        ->and($recebimento->itens)->toHaveCount(1)
        ->and((float) $recebimento->itens->first()->quantidade_recebida)->toBe(4.0);
});

it('registra_multiplos_recebimentos_parciais_ate_completar', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 3.0]);
    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 7.0]);

    expect($pedido->fresh()->statusRecebimento())->toBe(StatusRecebimentoPedido::Total);
});

it('rejeita_recebimento_que_excede_saldo_disponivel', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    // Recebe 8 primeiro
    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 8.0]);

    // Tenta receber 5 (saldo é só 2)
    expect(fn () => app(RegistrarRecebimentoAction::class)->execute(
        $pedido, $setup['almoxarife'], [$item->id => 5.0]
    ))->toThrow(ValidationException::class);
});

it('rejeita_recebimento_em_pc_nao_emitido', function () {
    $setup = f6_setup();

    $rascunho = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $setup['fornecedor']->id,
        'unidade_id' => $setup['unidade']->id,
        'criado_por' => $setup['compradora']->id,
    ]);

    $rascunho->itens()->create([
        'requisicao_id' => $setup['requisicao']->id,
        'item_requisicao_id' => $setup['itemReq']->id,
        'cotacao_id' => $setup['cotacao']->id,
        'descricao' => 'Item',
        'quantidade' => 10,
        'unidade_medida' => 'un',
        'valor_unitario' => 100,
        'valor_total' => 1000,
        'destino' => 'Depósito',
    ]);

    expect(fn () => app(RegistrarRecebimentoAction::class)->execute(
        $rascunho, $setup['almoxarife'], [$rascunho->itens->first()->id => 5.0]
    ))->toThrow(ValidationException::class);
});

it('rejeita_recebimento_sem_quantidades_validas', function () {
    $setup = f6_setup();
    $pedido = f6_emitirPC($setup);

    expect(fn () => app(RegistrarRecebimentoAction::class)->execute(
        $pedido, $setup['almoxarife'], []
    ))->toThrow(ValidationException::class);
});

// ─── Transições de Requisição ────────────────────────────────────────────────

it('recebimento_parcial_nao_transiciona_requisicao', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 6.0]);

    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::EmCompra);
});

it('recebimento_total_transiciona_requisicao_para_concluida', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 10.0]);

    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::Concluida);
});

it('recebimento_total_em_etapas_transiciona_requisicao_na_ultima_entrega', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 4.0]);
    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::EmCompra);

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 6.0]);
    expect($setup['requisicao']->fresh()->status)->toBe(StatusRequisicao::Concluida);
});

// ─── E-mail ──────────────────────────────────────────────────────────────────

it('envia_email_ao_solicitante_apos_recebimento_total', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    Mail::fake();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 10.0]);

    Mail::assertSent(PedidoCompraRecebido::class, fn ($mail) => $mail->hasTo($setup['solicitante']->email)
    );
});

it('nao_envia_email_em_recebimento_parcial', function () {
    $setup = f6_setup(quantidade: 10.0);
    $pedido = f6_emitirPC($setup);
    $item = $pedido->itens->first();

    Mail::fake();

    app(RegistrarRecebimentoAction::class)->execute($pedido, $setup['almoxarife'], [$item->id => 5.0]);

    Mail::assertNothingSent();
});

// ─── Rotas e Autorização ─────────────────────────────────────────────────────

it('rota_recebimentos_retorna_403_sem_perfil_almoxarife', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('almoxarife.recebimentos.index'))
        ->assertForbidden();
});

it('rota_recebimentos_retorna_200_com_perfil_almoxarife', function () {
    $setup = f6_setup();

    $this->actingAs($setup['almoxarife'])
        ->get(route('almoxarife.recebimentos.index'))
        ->assertSuccessful();
});

it('rota_registrar_retorna_403_sem_perfil_almoxarife', function () {
    $setup = f6_setup();
    $pedido = f6_emitirPC($setup);
    $outroUser = User::factory()->create();

    $this->actingAs($outroUser)
        ->get(route('almoxarife.recebimentos.registrar', $pedido->id))
        ->assertForbidden();
});

it('rota_registrar_retorna_403_para_almoxarife_de_outra_unidade', function () {
    $setup = f6_setup();
    $pedido = f6_emitirPC($setup);

    $outraUnidade = Unidade::factory()->create();
    $outroAlmox = User::factory()->create();
    $outroAlmox->unidades()->attach($outraUnidade->id, ['perfil' => Perfil::Almoxarife->value]);

    $this->actingAs($outroAlmox)
        ->get(route('almoxarife.recebimentos.registrar', $pedido->id))
        ->assertForbidden();
});
