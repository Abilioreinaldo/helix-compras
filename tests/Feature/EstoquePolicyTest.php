<?php

use App\Enums\Perfil;
use App\Livewire\Almoxarife\MapaEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| EstoquePolicy — Gate `estoque.gerenciar` (acesso ao módulo Almoxarife).
|--------------------------------------------------------------------------
|
| Cobre o ACESSO. As regras de ESTADO/WORKFLOW (status do pedido == Emitido, saldo, lote,
| quantidade, invariantes) seguem nos componentes/Actions (abort_unless/ValidationException)
| e são exercitadas pelas suítes de estoque existentes — NÃO viraram Gate (admin não fura).
|
*/

uses(RefreshDatabase::class);

function est_almoxarife(): User
{
    $user = User::factory()->create();
    $user->unidades()->attach(Unidade::factory()->create()->id, ['perfil' => Perfil::Almoxarife->value]);

    return $user;
}

it('almoxarife pode gerenciar estoque', function () {
    expect(est_almoxarife()->can('estoque.gerenciar'))->toBeTrue();
});

it('usuário comum não pode gerenciar estoque', function () {
    expect(User::factory()->create()->can('estoque.gerenciar'))->toBeFalse();
});

it('admin pode gerenciar estoque (Gate::before da fundação)', function () {
    expect(User::factory()->admin()->create()->can('estoque.gerenciar'))->toBeTrue();
});

it('mapa de estoque: almoxarife acessa, usuário comum recebe 403', function () {
    Livewire::actingAs(est_almoxarife())
        ->test(MapaEstoque::class)
        ->assertOk();

    Livewire::actingAs(User::factory()->create())
        ->test(MapaEstoque::class)
        ->assertForbidden();
});
