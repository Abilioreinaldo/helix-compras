<?php

use App\Actions\EmitirPedidoCompraAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Livewire\Almoxarife\RegistroRecebimento;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRequisicao;
use App\Models\LoteEstoque;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

/**
 * Monta um PC emitido (status Emitido) com um item vinculado a um catálogo controla_lote.
 *
 * @return array{almoxarife: User, pedido: PedidoCompra, item: ItemPedidoCompra}
 */
function rrl_setup(bool $controlaLote = true, float $quantidade = 10.0, float $valorTotal = 1000.0): array
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
        'descricao' => 'Insumo Recebimento UI',
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
        'destino' => 'Depósito Central',
        'item_catalogo_id' => $catalogo->id,
        'avulso' => false,
    ]);

    $pedido = app(EmitirPedidoCompraAction::class)->execute($pedido, $compradora);
    $item = $pedido->itens->first();

    return compact('almoxarife', 'pedido', 'item');
}

it('recebimento_coleta_lote_e_credita_lote_estoque', function () {
    $s = rrl_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);

    Livewire::actingAs($s['almoxarife'])
        ->test(RegistroRecebimento::class, ['id' => $s['pedido']->id])
        ->set("quantidades.{$s['item']->id}", '5')
        ->set("lotes.{$s['item']->id}.numero_lote", 'L-UI')
        ->set("lotes.{$s['item']->id}.validade", '2027-05-01')
        ->call('registrar')
        ->assertHasNoErrors();

    $lote = LoteEstoque::first();
    $saldo = SaldoEstoque::first();

    expect(LoteEstoque::count())->toBe(1)
        ->and($lote->numero_lote)->toBe('L-UI')
        ->and($lote->validade->format('Y-m-d'))->toBe('2027-05-01')
        ->and((float) $lote->quantidade)->toBe(5.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe((float) $saldo->quantidade);
});

it('recebimento_de_item_controla_lote_sem_numero_mostra_erro_e_nao_credita', function () {
    $s = rrl_setup(controlaLote: true, quantidade: 10.0, valorTotal: 1000.0);

    Livewire::actingAs($s['almoxarife'])
        ->test(RegistroRecebimento::class, ['id' => $s['pedido']->id])
        ->set("quantidades.{$s['item']->id}", '5')
        ->call('registrar')
        ->assertHasErrors("lotes.{$s['item']->id}.numero_lote");

    expect(LoteEstoque::count())->toBe(0)
        ->and(SaldoEstoque::count())->toBe(0);   // nada criado
});

it('recebimento_de_item_sem_controle_de_lote_nao_exige_lote', function () {
    $s = rrl_setup(controlaLote: false, quantidade: 10.0, valorTotal: 1000.0);

    Livewire::actingAs($s['almoxarife'])
        ->test(RegistroRecebimento::class, ['id' => $s['pedido']->id])
        ->set("quantidades.{$s['item']->id}", '5')
        ->call('registrar')
        ->assertHasNoErrors();

    expect(LoteEstoque::count())->toBe(0)
        ->and((float) SaldoEstoque::first()->quantidade)->toBe(5.0);
});
