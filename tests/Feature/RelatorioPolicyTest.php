<?php

use App\Enums\Perfil;
use App\Livewire\Relatorios\GastosCentroCusto;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| RelatorioPolicy — Gate `relatorio.ver` (relatórios consolidados da rede).
|--------------------------------------------------------------------------
|
| Espelha podeVerTodasUnidades() (admin ou compras sênior). O RelatorioRateioMensalCentral
| tem regra própria (admin OU gestor da unidade + scoping) e não usa este gate.
|
*/

uses(RefreshDatabase::class);

it('compradora sênior e admin veem os relatórios consolidados', function () {
    expect(User::factory()->compradora()->create()->can('relatorio.ver'))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('relatorio.ver'))->toBeTrue();
});

it('solicitante de uma unidade NÃO vê os relatórios consolidados', function () {
    $user = User::factory()->create();
    $user->unidades()->attach(Unidade::factory()->create()->id, ['perfil' => Perfil::Solicitante->value]);

    expect($user->can('relatorio.ver'))->toBeFalse();
});

it('usuário comum não vê os relatórios consolidados', function () {
    expect(User::factory()->create()->can('relatorio.ver'))->toBeFalse();
});

it('relatório consolidado: compradora acessa, usuário comum recebe 403', function () {
    Livewire::actingAs(User::factory()->compradora()->create())
        ->test(GastosCentroCusto::class)
        ->assertOk();

    Livewire::actingAs(User::factory()->create())
        ->test(GastosCentroCusto::class)
        ->assertForbidden();
});
