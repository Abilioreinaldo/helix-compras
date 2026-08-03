<?php

use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Isolamento cross-tenant da tela de administração de usuários (achado C2 da
 * revisão): um admin do tenant A não pode listar, editar nem excluir usuários
 * do tenant B. Antes da correção, ListaUsuarios consultava `User` sem nenhum
 * filtro de tenant (8 withoutGlobalScopes soltos).
 */
beforeEach(function () {
    // Tenant A (atacante) — o primeiro, reusado pela factory.
    $this->tenantA = Tenant::create(['slug' => 'alpha', 'name' => 'Alpha', 'status' => 'active']);
    $this->adminA = User::factory()->admin()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Admin Alpha', 'email' => 'admin@alpha.test',
    ]);

    // Tenant B (vítima).
    $this->tenantB = Tenant::create(['slug' => 'bravo', 'name' => 'Bravo', 'status' => 'active']);
    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Fulano Bravo', 'email' => 'fulano@bravo.test',
    ]);
});

it('não lista usuários de outro tenant', function () {
    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->assertSee('Admin Alpha')
        ->assertDontSee('Fulano Bravo');
});

it('não deixa buscar usuário de outro tenant', function () {
    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->set('busca', 'Fulano')
        ->assertDontSee('Fulano Bravo');
});

it('não deixa excluir usuário de outro tenant', function () {
    // findOrFail escopado não acha o userB → lança antes do delete (404 no HTTP).
    expect(fn () => Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('excluir', $this->userB->id))
        ->toThrow(ModelNotFoundException::class);

    // e o usuário do tenant B continua no banco, intacto
    expect(User::find($this->userB->id))->not->toBeNull();
});

it('não deixa abrir para edição um usuário de outro tenant', function () {
    expect(fn () => Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $this->userB->id))
        ->toThrow(ModelNotFoundException::class);
});
