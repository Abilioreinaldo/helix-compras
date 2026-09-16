<?php

use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Exceptions\IdentityConflictException;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * 3ª auditoria adversarial — achados 7 e 8 do Compras (tela de usuários).
 *
 * `users.email` é unique GLOBAL e a identidade é COMPARTILHADA pela suíte. Dois
 * vazamentos a partir do admin de UM tenant:
 *  7. o `Rule::unique('users','email')` rodava ANTES do UserService e respondia
 *     "Este e-mail já está em uso." — oráculo de quem tem conta em QUALQUER cliente;
 *  8. editar nome/e-mail de quem também é membro ativo de OUTRA empresa só era
 *     barrado para convidado (home noutro tenant) — o home daqui renomeava a
 *     identidade que a outra empresa usa.
 */
beforeEach(function () {
    $this->tenantA = Tenant::create(['slug' => 'alfa-id', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-id', 'name' => 'Bravo', 'status' => 'active']);

    TenantContext::set($this->tenantA->id);

    $this->adminA = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id, 'email' => 'admin@alfa.test']);

    // Conta que só existe no tenant B (outro cliente da suíte).
    $this->deOutroCliente = User::factory()->create(['tenant_id' => $this->tenantB->id, 'email' => 'segredo@bravo.test']);
});

// ───────── Achado 7: oráculo de e-mail global ─────────

it('criar com e-mail de conta de OUTRO cliente não confirma que a conta existe', function () {
    $tela = Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('name', 'Sonda')
        ->set('email', 'segredo@bravo.test')
        ->set('status', 'active')
        ->call('salvar');

    $erro = $tela->errors()->first('email');

    // A resposta é a mensagem genérica da fundação — que não afirma existência
    // nem repete o e-mail — e não a do validador ("já está em uso").
    expect($erro)->toBe(IdentityConflictException::forEmail()->getMessage())
        ->and($erro)->not->toContain('em uso')
        ->and($erro)->not->toContain('segredo@bravo.test')
        ->and(User::where('email', 'segredo@bravo.test')->count())->toBe(1);
});

it('trocar o e-mail para o de conta de OUTRO cliente responde com a MESMA mensagem genérica', function () {
    $local = User::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'local@alfa.test']);

    $tela = Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $local->id)
        ->set('email', 'segredo@bravo.test')
        ->call('salvar');

    expect($tela->errors()->first('email'))->toBe(IdentityConflictException::forEmail()->getMessage())
        ->and($local->fresh()->email)->toBe('local@alfa.test');
});

// ───────── Achado 8: identidade usada em outro tenant ─────────

it('não edita nome nem e-mail de quem também é membro ativo de outra empresa', function (string $campo, string $valor) {
    // Home AQUI (não é convidado), mas trabalha também no tenant B.
    $compartilhado = User::factory()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Nome Original', 'email' => 'comp@alfa.test',
    ]);
    $compartilhado->memberships()->syncWithoutDetaching([$this->tenantB->id => ['is_admin' => false, 'status' => 'active']]);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $compartilhado->id)
        ->set($campo, $valor)
        ->call('salvar')
        ->assertHasErrors($campo);

    expect($compartilhado->fresh()->name)->toBe('Nome Original')
        ->and($compartilhado->fresh()->email)->toBe('comp@alfa.test');
})->with([
    'nome' => ['name', 'Renomeado pelo A'],
    'e-mail' => ['email', 'sequestrado@alfa.test'],
]);

it('ainda permite ajustar o vínculo (admin/papéis) de quem participa de outra empresa', function () {
    $compartilhado = User::factory()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Nome Original', 'email' => 'comp@alfa.test',
    ]);
    $compartilhado->memberships()->syncWithoutDetaching([$this->tenantB->id => ['is_admin' => false, 'status' => 'active']]);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $compartilhado->id)
        ->set('isAdmin', true)
        ->call('salvar')
        ->assertHasNoErrors();

    TenantContext::set($this->tenantA->id);
    expect($compartilhado->fresh()->name)->toBe('Nome Original');
});

it('segue editando nome e e-mail de quem só pertence a esta empresa', function () {
    $soDaqui = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'So Daqui', 'email' => 'so@alfa.test']);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $soDaqui->id)
        ->set('name', 'So Daqui Renomeado')
        ->set('email', 'novo@alfa.test')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($soDaqui->fresh()->name)->toBe('So Daqui Renomeado')
        ->and($soDaqui->fresh()->email)->toBe('novo@alfa.test');
});
