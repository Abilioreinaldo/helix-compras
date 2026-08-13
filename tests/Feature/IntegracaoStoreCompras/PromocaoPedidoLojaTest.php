<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\PedidosLoja;
use App\Models\CentroCusto;
use App\Models\PedidoLojaRecebido;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Livewire\Livewire;

/**
 * ADR-015 — promoção do pedido da loja (inbox) a Requisicao pela compradora.
 */
function pedidoLojaNoInbox(string $code = 'PED-0001'): PedidoLojaRecebido
{
    return PedidoLojaRecebido::create([
        'request_code' => $code,
        'store_code' => 'LOJ-0001',
        'supplier_cnpj' => '61585865000151',
        'supplier_name' => 'Distribuidora ABC LTDA',
        'line_count' => 2,
        'total_estimated_cents' => 13600,
        'status' => PedidoLojaRecebido::STATUS_RECEBIDO,
        'recebido_em' => now(),
        'payload' => [
            'version' => 1,
            'request_code' => $code,
            'lines' => [
                ['product_code' => 'PRD-0001', 'product_name' => 'Cerveja Lata 350ml', 'qty_thousandths' => 24000, 'last_unit_cost_cents' => 400, 'line_total_cents' => 9600],
                ['product_code' => 'PRD-0002', 'product_name' => 'Salgadinho 80g', 'qty_thousandths' => 8000, 'last_unit_cost_cents' => 500, 'line_total_cents' => 4000],
            ],
        ],
    ]);
}

function compradoraComUnidade(): array
{
    $unidade = Unidade::factory()->create();
    $compradora = User::factory()->compradora()->create();
    $compradora->unidades()->attach($unidade->id, ['perfil' => Perfil::CompradoraSenior->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id, 'ativo' => true]);

    return [$compradora, $unidade, $centro];
}

it('promove o pedido da loja a requisição em rascunho com os itens do snapshot', function () {
    [$compradora, $unidade, $centro] = compradoraComUnidade();
    $pedido = pedidoLojaNoInbox();

    Livewire::actingAs($compradora)
        ->test(PedidosLoja::class)
        ->call('abrirPromocao', $pedido->id)
        ->set('unidadeId', $unidade->id)
        ->set('centroCustoId', $centro->id)
        ->call('promover')
        ->assertHasNoErrors();

    $pedido->refresh();
    expect($pedido->status)->toBe(PedidoLojaRecebido::STATUS_PROMOVIDO)
        ->and($pedido->requisicao_id)->not->toBeNull();

    $req = $pedido->requisicao;
    expect($req->status)->toBe(StatusRequisicao::Rascunho)
        ->and($req->solicitante_id)->toBe($compradora->id)
        ->and($req->unidade_id)->toBe($unidade->id)
        ->and($req->itens)->toHaveCount(2)
        ->and($req->itens->first()->avulso)->toBeTrue()
        ->and((float) $req->itens->first()->quantidade)->toBe(24.0)
        ->and((float) $req->itens->first()->valor_unitario_estimado)->toBe(4.0);
});

it('não promove duas vezes o mesmo pedido (guarda de status)', function () {
    [$compradora, $unidade, $centro] = compradoraComUnidade();
    $pedido = pedidoLojaNoInbox();
    $pedido->update(['status' => PedidoLojaRecebido::STATUS_PROMOVIDO]);

    Livewire::actingAs($compradora)
        ->test(PedidosLoja::class)
        ->call('abrirPromocao', $pedido->id)
        ->set('unidadeId', $unidade->id)
        ->set('centroCustoId', $centro->id)
        ->call('promover')
        ->assertHasErrors('promocao');

    expect(Requisicao::count())->toBe(0);
});

it('descarta um pedido recebido', function () {
    [$compradora] = compradoraComUnidade();
    $pedido = pedidoLojaNoInbox('PED-0009');

    Livewire::actingAs($compradora)
        ->test(PedidosLoja::class)
        ->call('descartar', $pedido->id)
        ->assertHasNoErrors();

    expect($pedido->refresh()->status)->toBe(PedidoLojaRecebido::STATUS_DESCARTADO);
});

it('bloqueia quem não é compradora sênior (403 no mount)', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    Livewire::actingAs($solicitante)
        ->test(PedidosLoja::class)
        ->assertForbidden();
});
