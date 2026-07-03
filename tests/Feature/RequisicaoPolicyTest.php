<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Models\CentroCusto;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| RequisicaoPolicy — autorização de Requisições de compra.
|--------------------------------------------------------------------------
|
| Cobre a camada extraída (Gate/Policy) nos cenários pedidos: solicitante da própria
| unidade, solicitante de outra unidade, compradora/admin e usuário sem perfil. A regra
| não mudou — a Policy delega aos helpers do User (papéis globais + vínculo de unidade) e
| ao status da requisição.
|
*/

uses(RefreshDatabase::class);

function rp_requisicao(Unidade $unidade, StatusRequisicao $status = StatusRequisicao::Rascunho): Requisicao
{
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    return Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => $status,
    ]);
}

function rp_userNaUnidade(Unidade $unidade, Perfil $perfil = Perfil::Solicitante): User
{
    $user = User::factory()->create();
    $user->unidades()->attach($unidade->id, ['perfil' => $perfil->value]);

    return $user;
}

// ─── view ────────────────────────────────────────────────────────────────────

it('solicitante da própria unidade pode ver a requisição', function () {
    $unidade = Unidade::factory()->create();
    $requisicao = rp_requisicao($unidade);

    expect(rp_userNaUnidade($unidade)->can('view', $requisicao))->toBeTrue();
});

it('solicitante de outra unidade NÃO pode ver a requisição', function () {
    $requisicao = rp_requisicao(Unidade::factory()->create());
    $deOutraUnidade = rp_userNaUnidade(Unidade::factory()->create());

    expect($deOutraUnidade->can('view', $requisicao))->toBeFalse();
});

it('compradora sênior e admin veem requisição de qualquer unidade', function () {
    $requisicao = rp_requisicao(Unidade::factory()->create());

    expect(User::factory()->compradora()->create()->can('view', $requisicao))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('view', $requisicao))->toBeTrue();
});

it('usuário sem perfil/unidade NÃO pode ver a requisição', function () {
    $requisicao = rp_requisicao(Unidade::factory()->create());

    expect(User::factory()->create()->can('view', $requisicao))->toBeFalse();
});

// ─── update (status) ───────────────────────────────────────────────────────────

it('editar só é permitido enquanto o status permite edição', function () {
    $unidade = Unidade::factory()->create();
    $user = rp_userNaUnidade($unidade);

    $rascunho = rp_requisicao($unidade, StatusRequisicao::Rascunho);   // permiteEdicao = true
    $aprovada = rp_requisicao($unidade, StatusRequisicao::Aprovada);   // permiteEdicao = false

    expect($user->can('update', $rascunho))->toBeTrue()
        ->and($user->can('update', $aprovada))->toBeFalse();
});

// ─── update cross-unidade (anti-IDOR) ────────────────────────────────────────

it('solicitante de outra unidade NÃO pode editar, mesmo com status editável (anti-IDOR)', function () {
    $requisicao = rp_requisicao(Unidade::factory()->create(), StatusRequisicao::Rascunho);
    $deOutraUnidade = rp_userNaUnidade(Unidade::factory()->create());

    expect($deOutraUnidade->can('update', $requisicao))->toBeFalse();
});

it('formulário barra a edição de requisição de OUTRA unidade (403)', function () {
    $requisicao = rp_requisicao(Unidade::factory()->create(), StatusRequisicao::Rascunho);
    $deOutraUnidade = rp_userNaUnidade(Unidade::factory()->create());

    Livewire::actingAs($deOutraUnidade)
        ->test(FormularioRequisicao::class, ['id' => $requisicao->id])
        ->assertForbidden();
});

it('solicitante edita requisição da própria unidade normalmente', function () {
    $unidade = Unidade::factory()->create();
    $requisicao = rp_requisicao($unidade, StatusRequisicao::Rascunho);

    Livewire::actingAs(rp_userNaUnidade($unidade))
        ->test(FormularioRequisicao::class, ['id' => $requisicao->id])
        ->assertOk();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('qualquer usuário autenticado pode criar requisição', function () {
    expect(User::factory()->create()->can('create', Requisicao::class))->toBeTrue();
});
