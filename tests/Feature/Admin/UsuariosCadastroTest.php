<?php

use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Usuário criado pela tela de administração precisa nascer com a membership
 * tenant_user (achado ALTO do Compras): sem ela, SetActiveTenant não acha
 * tenant ativo e o usuário toma 403 no primeiro login.
 */
beforeEach(function () {
    $this->tenant = Tenant::create(['slug' => 'alpha', 'name' => 'Alpha', 'status' => 'active']);

    // O beforeEach global (tests/Pest.php) deixa no contexto o tenant canônico
    // "comendador"; este arquivo trabalha em `alpha`. Desde a fundação v0.2.0 o
    // UserService recusa um tenant_id divergente do contexto (o tenant nunca vem
    // do chamador), então o contexto tem de ser o do admin — que é exatamente o
    // que o SetActiveTenant faz no request real.
    TenantContext::set($this->tenant->id);

    $this->admin = User::factory()->admin()->create([
        'tenant_id' => $this->tenant->id, 'email' => 'admin@alpha.test',
    ]);
});

it('cria usuário com membership ativa no tenant do admin', function () {
    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('name', 'Novo Usuário')
        ->set('email', 'novo@alpha.test')
        ->set('status', 'active')
        ->set('isAdmin', false)
        ->call('salvar');

    $novo = User::where('email', 'novo@alpha.test')->firstOrFail();

    // membership ativa no tenant certo — sem isto o login daria 403
    expect(DB::table('tenant_user')->where('user_id', $novo->id)
        ->where('tenant_id', $this->tenant->id)->where('status', 'active')->exists())->toBeTrue()
        ->and($novo->isAdminForActiveTenant())->toBeFalse();
});

it('cria admin com is_admin refletido no pivot (não só na coluna)', function () {
    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('name', 'Novo Admin')
        ->set('email', 'novoadmin@alpha.test')
        ->set('status', 'active')
        ->set('isAdmin', true)
        ->call('salvar');

    $novo = User::where('email', 'novoadmin@alpha.test')->firstOrFail();

    expect($novo->isAdminForActiveTenant())->toBeTrue();
});

it('editar is_admin sincroniza o pivot (revogar desescala de verdade)', function () {
    $alvo = User::factory()->admin()->create([
        'tenant_id' => $this->tenant->id, 'email' => 'alvo@alpha.test',
    ]);
    expect($alvo->isAdminForActiveTenant())->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $alvo->id)
        ->set('isAdmin', false)
        ->call('salvar');

    expect($alvo->fresh()->isAdminForActiveTenant())->toBeFalse();
});
