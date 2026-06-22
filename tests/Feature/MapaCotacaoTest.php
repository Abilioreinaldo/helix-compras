<?php

use App\Actions\RegistrarCotacaoAction;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\MapaCotacao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mc_requisicaoEmCotacao(): Requisicao
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['minimo_cotacoes' => 3, 'is_emergencial' => false, 'ativo' => true]);

    $req = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'codigo' => 'REQ-MC-0001',
        'urgente' => false,
        'is_emergencial' => false,
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
    ]);

    $req->itens()->create(['descricao' => 'Mouse', 'quantidade' => 5, 'unidade_medida' => 'un', 'valor_unitario_estimado' => 30, 'avulso' => true]);
    $req->itens()->create(['descricao' => 'Teclado', 'quantidade' => 10, 'unidade_medida' => 'un', 'valor_unitario_estimado' => 60, 'avulso' => true]);

    return $req->load('itens');
}

function mc_cotacao(Requisicao $req, ?float $valor, User $criador): Cotacao
{
    return Cotacao::factory()->create([
        'requisicao_id' => $req->id,
        'fornecedor_id' => Fornecedor::factory()->create()->id,
        'criada_por' => $criador->id,
        'valor' => $valor,
    ]);
}

it('renderiza o mapa com vários fornecedores', function () {
    $compradora = User::factory()->compradora()->create();
    $req = mc_requisicaoEmCotacao();
    $fA = mc_cotacao($req, 100.00, $compradora);
    $fB = mc_cotacao($req, 90.00, $compradora);
    $fC = mc_cotacao($req, 110.00, $compradora);

    Livewire::actingAs($compradora)
        ->test(MapaCotacao::class, ['requisicaoId' => $req->id])
        ->assertOk()
        ->assertSee($fA->fornecedor->nome_fantasia)
        ->assertSee($fB->fornecedor->nome_fantasia)
        ->assertSee($fC->fornecedor->nome_fantasia);
});

it('identifica a melhor compra (menor valor confirmado)', function () {
    $compradora = User::factory()->compradora()->create();
    $req = mc_requisicaoEmCotacao();
    mc_cotacao($req, 100.00, $compradora);
    $barata = mc_cotacao($req, 90.00, $compradora);
    mc_cotacao($req, 110.00, $compradora);

    $componente = Livewire::actingAs($compradora)
        ->test(MapaCotacao::class, ['requisicaoId' => $req->id]);

    expect($componente->instance()->melhorCotacaoId())->toBe($barata->id);
});

it('marca a cotação como vencedora', function () {
    $compradora = User::factory()->compradora()->create();
    $req = mc_requisicaoEmCotacao();
    $barata = mc_cotacao($req, 90.00, $compradora);

    Livewire::actingAs($compradora)
        ->test(MapaCotacao::class, ['requisicaoId' => $req->id])
        ->call('marcarVencedora', $barata->id);

    expect($barata->fresh()->vencedora)->toBeTrue();
});

it('não há vencedor selecionável sem cotação confirmada', function () {
    $compradora = User::factory()->compradora()->create();
    $req = mc_requisicaoEmCotacao();
    mc_cotacao($req, null, $compradora); // só sugestão/aguardando
    mc_cotacao($req, null, $compradora);

    $componente = Livewire::actingAs($compradora)
        ->test(MapaCotacao::class, ['requisicaoId' => $req->id]);

    expect($componente->instance()->temCotacaoConfirmada())->toBeFalse();
});

it('bloqueia quem não é compradora (403)', function () {
    $usuario = User::factory()->create();
    $req = mc_requisicaoEmCotacao();

    $this->actingAs($usuario)
        ->get(route('compradora.mapa-cotacao', ['requisicaoId' => $req->id]))
        ->assertForbidden();
});

it('monta a matriz por item e destaca o menor de cada linha e o menor total', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $req = mc_requisicaoEmCotacao();
    $itens = $req->itens->values();

    $fA = Fornecedor::factory()->homologado()->create(['nome_fantasia' => 'Fornecedor A']);
    $fB = Fornecedor::factory()->homologado()->create(['nome_fantasia' => 'Fornecedor B']);

    // A: Mouse 30×5=150, Teclado 60×10=600 → total 750
    $cotA = app(RegistrarCotacaoAction::class)->execute($req, $fA, 0.0, null, null, null, null, precosPorItem: [$itens[0]->id => 30, $itens[1]->id => 60]);
    // B: Mouse 35×5=175, Teclado 58,50×10=585 → total 760
    app(RegistrarCotacaoAction::class)->execute($req, $fB, 0.0, null, null, null, null, precosPorItem: [$itens[0]->id => 35, $itens[1]->id => 58.50]);

    $comp = Livewire::actingAs($compradora)->test(MapaCotacao::class, ['requisicaoId' => $req->id]);

    $comp->assertOk()
        ->assertSee('Fornecedor A')
        ->assertSee('Fornecedor B')
        ->assertSee('R$ 150,00')   // melhor do Mouse (A)
        ->assertSee('R$ 585,00');  // melhor do Teclado (B)

    // Menor total geral = A (750 < 760).
    expect($comp->instance()->melhorCotacaoId())->toBe($cotA->id);
});
