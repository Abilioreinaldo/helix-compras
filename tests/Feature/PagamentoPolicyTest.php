<?php

use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| PagamentoPolicy — autorização do módulo Financeiro.
|--------------------------------------------------------------------------
|
| Cobre a camada extraída (Gate/Policy) diretamente, nos mesmos cenários que
| os componentes Livewire já exercitam: financeiro, admin, compradora (sem
| financeiro) e usuário comum. A regra não mudou — a Policy delega aos helpers
| podeVerPagamentos()/podeGerenciarPagamentos() do User.
|
*/

uses(RefreshDatabase::class);

it('financeiro pode ver e gerenciar pagamentos', function () {
    $user = User::factory()->financeiro()->create();

    expect($user->can('viewAny', Pagamento::class))->toBeTrue()
        ->and($user->can('manage', Pagamento::class))->toBeTrue();
});

it('admin pode ver e gerenciar pagamentos', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('viewAny', Pagamento::class))->toBeTrue()
        ->and($user->can('manage', Pagamento::class))->toBeTrue();
});

it('compradora sênior (sem papel financeiro) não pode ver nem gerenciar pagamentos', function () {
    $user = User::factory()->compradora()->create();

    expect($user->can('viewAny', Pagamento::class))->toBeFalse()
        ->and($user->can('manage', Pagamento::class))->toBeFalse();
});

it('usuário comum não pode ver nem gerenciar pagamentos', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Pagamento::class))->toBeFalse()
        ->and($user->can('manage', Pagamento::class))->toBeFalse();
});
