<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\ListaCotacoes;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function lc_requisicao(string $codigo, StatusRequisicao $status): Requisicao
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['minimo_cotacoes' => 3, 'is_emergencial' => false, 'ativo' => true]);

    return Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => $status,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => $codigo,
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
    ]);
}

it('compradora vê a lista de cotações', function () {
    $compradora = User::factory()->compradora()->create();
    lc_requisicao('REQ-LC-0001', StatusRequisicao::EmCotacao);

    Livewire::actingAs($compradora)
        ->test(ListaCotacoes::class)
        ->assertOk()
        ->assertSee('REQ-LC-0001');
});

it('não-compradora recebe 403 na rota de cotações', function () {
    $usuario = User::factory()->create(); // sem is_compradora

    $this->actingAs($usuario)->get(route('cotacoes.index'))->assertForbidden();
});

it('filtra cotações por situação', function () {
    $compradora = User::factory()->compradora()->create();
    lc_requisicao('REQ-LC-EMCOT', StatusRequisicao::EmCotacao);
    lc_requisicao('REQ-LC-CONCL', StatusRequisicao::CotacaoConcluida);

    // Sem filtro: vê as duas.
    Livewire::actingAs($compradora)
        ->test(ListaCotacoes::class)
        ->assertSee('REQ-LC-EMCOT')
        ->assertSee('REQ-LC-CONCL');

    // Filtrando por concluídas: só a concluída.
    Livewire::actingAs($compradora)
        ->test(ListaCotacoes::class)
        ->set('filtroStatus', 'cotacao_concluida')
        ->assertSee('REQ-LC-CONCL')
        ->assertDontSee('REQ-LC-EMCOT');
});

it('não lista requisições fora da fase de cotação', function () {
    $compradora = User::factory()->compradora()->create();
    lc_requisicao('REQ-LC-RASCUNHO', StatusRequisicao::Rascunho);
    lc_requisicao('REQ-LC-APROVADA', StatusRequisicao::Aprovada);

    Livewire::actingAs($compradora)
        ->test(ListaCotacoes::class)
        ->assertDontSee('REQ-LC-RASCUNHO')
        ->assertDontSee('REQ-LC-APROVADA');
});
