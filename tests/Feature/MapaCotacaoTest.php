<?php

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

    return Requisicao::create([
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
