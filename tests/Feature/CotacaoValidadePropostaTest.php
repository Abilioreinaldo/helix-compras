<?php

use App\Actions\RegistrarCotacaoAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\GestaoCotacoes;
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

function cvp_requisicaoEmCotacao(): Requisicao
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false,
        'ativo' => true, 'minimo_cotacoes' => 3,
    ]);

    return Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-CVP-'.fake()->unique()->numerify('#####'),
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'triagem_iniciada_em' => now(),
    ]);
}

it('registrar_cotacao_persiste_validade_proposta_e_prazo', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);
    $requisicao = cvp_requisicaoEmCotacao();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    $cotacao = app(RegistrarCotacaoAction::class)->execute(
        $requisicao, $fornecedor, 1500.00, null, 7, 'obs', '2026-12-31'
    );

    expect($cotacao->prazo_entrega_dias)->toBe(7)
        ->and($cotacao->validade_proposta->format('Y-m-d'))->toBe('2026-12-31');
});

it('registrar_cotacao_sem_validade_fica_nula', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);
    $requisicao = cvp_requisicaoEmCotacao();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    $cotacao = app(RegistrarCotacaoAction::class)->execute($requisicao, $fornecedor, 1000.00);

    expect($cotacao->validade_proposta)->toBeNull();
});

it('gestao_cotacoes_coleta_validade_da_proposta', function () {
    $compradora = User::factory()->compradora()->create();
    $requisicao = cvp_requisicaoEmCotacao();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    Livewire::actingAs($compradora)
        ->test(GestaoCotacoes::class, ['id' => $requisicao->id])
        ->set('fornecedorId', $fornecedor->id)
        ->set('valor', '1200')
        ->set('prazoEntregaDias', '10')
        ->set('validadeProposta', '2027-03-15')
        ->call('registrarCotacao')
        ->assertHasNoErrors();

    $cotacao = Cotacao::first();
    expect($cotacao->validade_proposta->format('Y-m-d'))->toBe('2027-03-15')
        ->and($cotacao->prazo_entrega_dias)->toBe(10);
});

it('gestao_cotacoes_rejeita_validade_invalida', function () {
    $compradora = User::factory()->compradora()->create();
    $requisicao = cvp_requisicaoEmCotacao();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    Livewire::actingAs($compradora)
        ->test(GestaoCotacoes::class, ['id' => $requisicao->id])
        ->set('fornecedorId', $fornecedor->id)
        ->set('valor', '1200')
        ->set('validadeProposta', 'data-invalida')
        ->call('registrarCotacao')
        ->assertHasErrors('validadeProposta');

    expect(Cotacao::count())->toBe(0);
});
