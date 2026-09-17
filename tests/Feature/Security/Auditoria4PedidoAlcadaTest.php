<?php

/**
 * 4ª auditoria adversarial — COMPRAS-1 (ALTO) e COMPRAS-V1 (MÉDIO).
 *
 * As sondas P4-T1, P4-T1b e V4-A1 PROVARAM o ataque; aqui elas estão invertidas:
 * o ataque tem de FALHAR.
 *
 *  - COMPRAS-1: a quantidade do item do pedido vinha do snapshot Livewire e definia
 *    `valor_total`; o teto de alçada, o contas a pagar e o desmembramento liam o
 *    `valor_total` adulterado.
 *  - COMPRAS-V1: a rejeição por linha na aprovação não reduzia o teto do pedido e o
 *    preço do PC não estava preso ao preço cotado do item.
 */

use App\Actions\AprovarEtapaAction;
use App\Actions\CriarRascunhoPedidoAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\FormularioPedidoCompra;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRequisicao;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

/**
 * Requisição aprovada com 1 item (qtd 10) e cotação vencedora de R$ 1.000 (legado, sem
 * preço por item) + rascunho de pedido com o item a R$ 100.
 *
 * @return array{compradora: User, pedido: PedidoCompra, item: ItemPedidoCompra, requisicao: Requisicao, itemReq: ItemRequisicao, cotacao: Cotacao, fornecedor: Fornecedor}
 */
function a4_cenarioPedido(): array
{
    $compradora = User::factory()->compradora()->create();
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $unidade = Unidade::factory()->create();
    $requisicao = Requisicao::factory()->create(['unidade_id' => $unidade->id, 'status' => StatusRequisicao::Aprovada]);
    $itemReq = ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 10]);
    $cotacao = Cotacao::factory()->create(['requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'valor' => 1000, 'vencedora' => true]);
    $pedido = PedidoCompra::factory()->create(['fornecedor_id' => $fornecedor->id, 'unidade_id' => $unidade->id]);
    $item = ItemPedidoCompra::factory()->create([
        'pedido_compra_id' => $pedido->id, 'requisicao_id' => $requisicao->id, 'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id, 'quantidade' => 10, 'valor_unitario' => 100, 'valor_total' => 1000, 'destino' => 'Obra 1',
    ]);

    return compact('compradora', 'pedido', 'item', 'requisicao', 'itemReq', 'cotacao', 'fornecedor');
}

/**
 * Fluxo real da V4-A1: 2 itens cotados a R$ 500 cada (cotação de R$ 1.000), aprovador
 * rejeita o item 2 por linha, compradora gera o rascunho.
 *
 * @return array{compradora: User, pedido: PedidoCompra}
 */
function a4_cenarioRejeicaoPorLinha(): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);
    $compradora = User::factory()->compradora()->create();
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true]);
    $requisicao = Requisicao::factory()->create([
        'unidade_id' => $unidade->id, 'solicitante_id' => $solicitante->id,
        'centro_custo_id' => CentroCusto::factory()->create(['unidade_id' => $unidade->id])->id,
        'status' => StatusRequisicao::AguardandoAprovacao, 'faixa_alcada_id' => $faixa->id, 'ciclo_aprovacao' => 1, 'codigo' => 'REQ-A4-1',
    ]);
    $mantido = ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 1, 'descricao' => 'item mantido']);
    $rejeitado = ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 1, 'descricao' => 'item rejeitado']);
    $cotacao = Cotacao::factory()->create(['requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'valor' => 1000, 'vencedora' => true]);
    $cotacao->itensCotacao()->create(['item_requisicao_id' => $mantido->id, 'valor_unitario' => 500]);
    $cotacao->itensCotacao()->create(['item_requisicao_id' => $rejeitado->id, 'valor_unitario' => 500]);
    Aprovacao::create([
        'requisicao_id' => $requisicao->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1, 'nivel_exigido' => NivelAlcada::Gestor->value,
        'obrigatoria_emergencial' => false, 'status' => StatusAprovacao::Pendente->value,
    ]);

    test()->actingAs($aprovador);
    app(AprovarEtapaAction::class)->execute($requisicao, $aprovador, 'ok só o item 1', [$rejeitado->id => 'não precisa']);
    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::Aprovada);

    test()->actingAs($compradora);
    $pedido = app(CriarRascunhoPedidoAction::class)->execute($fornecedor, collect([$requisicao->fresh()]), $compradora);

    return compact('compradora', 'pedido', 'cotacao', 'mantido', 'rejeitado', 'requisicao');
}

// ─── COMPRAS-1 ───────────────────────────────────────────────────────────────

it('COMPRAS-1: a quantidade do item do pedido não é editável pelo snapshot Livewire (propriedade trancada)', function () {
    $c = a4_cenarioPedido();

    expect(fn () => Livewire::actingAs($c['compradora'])
        ->test(FormularioPedidoCompra::class, ['id' => $c['pedido']->id])
        ->set('itens.0.quantidade', '0.01'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $linha = DB::table('itens_pedido_compra')->where('id', $c['item']->id)->first();
    expect((float) $linha->quantidade)->toBe(10.0)->and((float) $linha->valor_total)->toBe(1000.0);
});

it('COMPRAS-1: salvar calcula valor_total com a quantidade DO BANCO × unitário digitado', function () {
    $c = a4_cenarioPedido();

    Livewire::actingAs($c['compradora'])
        ->test(FormularioPedidoCompra::class, ['id' => $c['pedido']->id])
        ->set('valores.0', '50')
        ->set('destinos.0', 'Obra 2')
        ->call('salvar')
        ->assertHasNoErrors();

    $linha = DB::table('itens_pedido_compra')->where('id', $c['item']->id)->first();
    expect((float) $linha->quantidade)->toBe(10.0)
        ->and((float) $linha->valor_unitario)->toBe(50.0)
        ->and((float) $linha->valor_total)->toBe(500.0)
        ->and($linha->destino)->toBe('Obra 2');
});

it('COMPRAS-1: a emissão recalcula quantidade × unitário no servidor — valor_total adulterado no banco não fura o teto', function () {
    $c = a4_cenarioPedido();

    // Estado que o ataque da sonda P4-T1b deixava gravado: qtd 10 × R$ 10.000 com valor_total = 100.
    DB::table('itens_pedido_compra')->where('id', $c['item']->id)->update(['valor_unitario' => 10000, 'valor_total' => 100]);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($c['pedido'], $c['compradora']))
        ->toThrow(ValidationException::class, 'excede o valor aprovado');

    expect(DB::table('pedidos_compra')->where('id', $c['pedido']->id)->value('status'))->toBe('rascunho')
        ->and(Pagamento::where('pedido_compra_id', $c['pedido']->id)->exists())->toBeFalse();
});

it('COMPRAS-1: pedido JÁ emitido com valor_total adulterado conta pelo valor real no teto de desmembramento', function () {
    $c = a4_cenarioPedido();

    // Pedido irmão já emitido: qtd 9 × R$ 100 = R$ 900 reais, mas valor_total gravado = 9.
    $emitido = PedidoCompra::factory()->emitido()->create(['fornecedor_id' => $c['fornecedor']->id, 'unidade_id' => $c['pedido']->unidade_id]);
    ItemPedidoCompra::factory()->create([
        'pedido_compra_id' => $emitido->id, 'requisicao_id' => $c['requisicao']->id, 'item_requisicao_id' => $c['itemReq']->id,
        'cotacao_id' => $c['cotacao']->id, 'quantidade' => 9, 'valor_unitario' => 100, 'valor_total' => 9, 'destino' => 'Obra 1',
    ]);

    // Este rascunho: 10 × 100 = 1.000 → 900 + 1.000 estoura o teto de 1.000.
    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($c['pedido'], $c['compradora']))
        ->toThrow(ValidationException::class, 'excede o valor aprovado');
});

it('COMPRAS-1: emissão honesta grava valor_total = quantidade × unitário e o contas a pagar sai pelo valor real', function () {
    $c = a4_cenarioPedido();
    DB::table('itens_pedido_compra')->where('id', $c['item']->id)->update(['valor_unitario' => 90, 'valor_total' => 1]);

    $pedido = app(EmitirPedidoCompraAction::class)->execute($c['pedido'], $c['compradora']);

    expect($pedido->status->value)->toBe('emitido')
        ->and((float) DB::table('itens_pedido_compra')->where('id', $c['item']->id)->value('valor_total'))->toBe(900.0)
        ->and((float) Pagamento::where('pedido_compra_id', $pedido->id)->value('valor_total'))->toBe(900.0);
});

it('COMPRAS-1: a emissão barra item de pedido com quantidade acima da quantidade aprovada na requisição', function () {
    $c = a4_cenarioPedido();
    // 50 × R$ 10 = R$ 500 cabe no teto, mas a requisição aprovou 10 unidades.
    DB::table('itens_pedido_compra')->where('id', $c['item']->id)->update(['quantidade' => 50, 'valor_unitario' => 10, 'valor_total' => 500]);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($c['pedido'], $c['compradora']))
        ->toThrow(ValidationException::class, 'quantidade');
});

// ─── COMPRAS-V1 ──────────────────────────────────────────────────────────────

it('COMPRAS-V1: o rascunho nasce com o preço cotado do item (não com zero)', function () {
    $c = a4_cenarioRejeicaoPorLinha();

    $linha = $c['pedido']->itens()->sole();
    expect((float) $linha->valor_unitario)->toBe(500.0)->and((float) $linha->valor_total)->toBe(500.0);
});

it('COMPRAS-V1: compradora não emite o item a um preço acima do cotado (uso normal da tela)', function () {
    $c = a4_cenarioRejeicaoPorLinha();

    $tela = Livewire::actingAs($c['compradora'])
        ->test(FormularioPedidoCompra::class, ['id' => $c['pedido']->id])
        ->set('valores.0', '1000')
        ->set('destinos.0', 'Obra')
        ->call('emitir');

    expect($tela->errors()->first('emissao'))->toContain('preço cotado')
        ->and(DB::table('pedidos_compra')->where('id', $c['pedido']->id)->value('status'))->toBe('rascunho');
});

it('COMPRAS-V1: item rejeitado por linha sai do teto — o gasto cortado pelo aprovador não volta', function () {
    $c = a4_cenarioRejeicaoPorLinha();

    // Tolerância de preço folgada de propósito: isola o TETO (o preço por item é outro controle).
    config()->set('compras.pedido.tolerancia_preco_cotado', 5.0);

    $pedido = $c['pedido'];
    $pedido->itens()->update(['valor_unitario' => 1000, 'valor_total' => 1000, 'destino' => 'Obra']);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($pedido, $c['compradora']))
        ->toThrow(ValidationException::class, 'excede o valor aprovado (R$ 500,00)');
});

it('COMPRAS-V1: item rejeitado por linha que reaparece no pedido é barrado na emissão', function () {
    $c = a4_cenarioRejeicaoPorLinha();

    $c['pedido']->itens()->update(['destino' => 'Obra']);
    $c['pedido']->itens()->create([
        'requisicao_id' => $c['requisicao']->id, 'item_requisicao_id' => $c['rejeitado']->id, 'cotacao_id' => $c['cotacao']->id,
        'descricao' => 'item rejeitado', 'quantidade' => 1, 'unidade_medida' => 'un', 'valor_unitario' => 1, 'valor_total' => 1, 'destino' => 'Obra',
    ]);

    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($c['pedido'], $c['compradora']))
        ->toThrow(ValidationException::class, 'rejeitado');
});

it('COMPRAS-V1: preço igual ao cotado emite; a tolerância vem de config literal (default 0)', function () {
    $c = a4_cenarioRejeicaoPorLinha();

    expect((require base_path('config/compras.php'))['pedido']['tolerancia_preco_cotado'])->toBe(0.0);

    Livewire::actingAs($c['compradora'])
        ->test(FormularioPedidoCompra::class, ['id' => $c['pedido']->id])
        ->set('destinos.0', 'Obra')
        ->call('emitir')
        ->assertHasNoErrors();

    expect(DB::table('pedidos_compra')->where('id', $c['pedido']->id)->value('status'))->toBe('emitido');
});

it('COMPRAS-V1: cotação legada (sem preço por item) com rejeição por linha reduz o teto na proporção do valor estimado', function () {
    $c = a4_cenarioPedido();
    // Segundo item da requisição, rejeitado por linha. Estimados: mantido 10×60=600, rejeitado 1×400=400.
    $c['itemReq']->update(['valor_unitario_estimado' => 60]);
    ItemRequisicao::factory()->create([
        'requisicao_id' => $c['requisicao']->id, 'quantidade' => 1, 'valor_unitario_estimado' => 400,
        'rejeitado_em' => now(), 'rejeitado_por' => $c['compradora']->id, 'motivo_rejeicao' => 'corte',
    ]);

    // Pedido a R$ 1.000 (cotação cheia) — o teto proporcional é R$ 600.
    expect(fn () => app(EmitirPedidoCompraAction::class)->execute($c['pedido'], $c['compradora']))
        ->toThrow(ValidationException::class, 'excede o valor aprovado (R$ 600,00)');
});
