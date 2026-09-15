<?php

use App\Enums\Perfil;
use App\Livewire\Relatorios\GastosCentroCusto;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Relatórios consolidados da rede — permissão do catálogo `compras.manage`.
|--------------------------------------------------------------------------
|
| Antes era o Gate `relatorio.ver` (RelatorioPolicy), que espelhava exatamente
| podeVerTodasUnidades() = compras.manage (admin ou compras sênior); os componentes
| checam agora a permissão do catálogo direto. O RelatorioRateioMensalCentral tem regra
| própria (admin OU gestor da unidade + scoping) e não usa esta permissão.
|
*/

uses(RefreshDatabase::class);

it('compradora sênior e admin veem os relatórios consolidados', function () {
    expect(User::factory()->compradora()->create()->can('compras.manage'))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('compras.manage'))->toBeTrue();
});

it('solicitante de uma unidade NÃO vê os relatórios consolidados', function () {
    $user = User::factory()->create();
    $user->unidades()->attach(Unidade::factory()->create()->id, ['perfil' => Perfil::Solicitante->value]);

    expect($user->can('compras.manage'))->toBeFalse();
});

it('usuário comum não vê os relatórios consolidados', function () {
    expect(User::factory()->create()->can('compras.manage'))->toBeFalse();
});

it('relatório consolidado: compradora acessa, usuário comum recebe 403', function () {
    Livewire::actingAs(User::factory()->compradora()->create())
        ->test(GastosCentroCusto::class)
        ->assertOk();

    Livewire::actingAs(User::factory()->create())
        ->test(GastosCentroCusto::class)
        ->assertForbidden();
});
