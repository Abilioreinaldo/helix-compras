<?php

use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Fundação v0.5.0 — ALCANCE do vínculo. Quem é criado por ATALHO (User::create com
 * tenant_id, seeder, importação) nasce com vínculo `pending_scope`: ligado à empresa,
 * mas SEM acesso, até o admin declarar o alcance. A tela de usuários mostra esses
 * vínculos numa seção própria e os libera com MembershipService::setScope.
 *
 * O alcance do Compras é sempre CORPORATIVO (o recorte por unidade é unidade_user).
 */
beforeEach(function () {
    $this->tenantA = Tenant::create(['slug' => 'alfa-ps', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-ps', 'name' => 'Bravo', 'status' => 'active']);

    TenantContext::set($this->tenantA->id);

    $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id, 'email' => 'admin@alfa-ps.test']);
});

/** Usuário criado por ATALHO: a fundação grava o vínculo como `pending_scope`. */
function ps_pendente(string $tenantId, string $email): User
{
    $user = User::forceCreate([
        'tenant_id' => $tenantId,
        'name' => 'Pendente '.$email,
        'email' => $email,
        'password' => bcrypt('x'),
        'status' => 'active',
        'is_admin' => false,
        'precisa_trocar_senha' => false,
    ]);

    expect(DB::table('tenant_user')->where('user_id', $user->id)->where('tenant_id', $tenantId)->value('status'))
        ->toBe(User::MEMBERSHIP_PENDING_SCOPE);

    return $user;
}

it('vínculo pendente não entra na lista de usuários, mas aparece na seção própria', function () {
    $pendente = ps_pendente($this->tenantA->id, 'pendente@alfa-ps.test');

    $tela = Livewire::actingAs($this->admin)->test(ListaUsuarios::class);

    expect($tela->viewData('usuarios')->pluck('id')->all())->not->toContain($pendente->id)
        ->and($tela->viewData('pendentes')->pluck('id')->all())->toContain($pendente->id);
});

it('a seção de pendentes é só desta empresa', function () {
    $daqui = ps_pendente($this->tenantA->id, 'daqui@alfa-ps.test');
    $deOutra = ps_pendente($this->tenantB->id, 'dela@bravo-ps.test');

    $tela = Livewire::actingAs($this->admin)->test(ListaUsuarios::class);

    expect($tela->viewData('pendentes')->pluck('id')->all())
        ->toContain($daqui->id)
        ->not->toContain($deOutra->id);
});

it('definir alcance ativa o vínculo como corporativo e audita', function () {
    $pendente = ps_pendente($this->tenantA->id, 'pendente@alfa-ps.test');

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('definirAlcance', $pendente->id)
        ->assertOk();

    $vinculo = DB::table('tenant_user')->where('user_id', $pendente->id)->where('tenant_id', $this->tenantA->id)->first();

    expect($vinculo->status)->toBe('active')
        ->and($vinculo->access_scope)->toBe(User::SCOPE_CORPORATE)
        ->and($vinculo->branch_id)->toBeNull()
        ->and($pendente->fresh()->belongsToTenant($this->tenantA->id))->toBeTrue()
        ->and(DB::table('audit_logs')
            ->where('action', 'user.membership_scope_changed')
            ->where('tenant_id', $this->tenantA->id)
            ->exists())->toBeTrue();
});

it('não libera vínculo pendente de OUTRA empresa (anti-IDOR)', function () {
    $deOutra = ps_pendente($this->tenantB->id, 'dela@bravo-ps.test');

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('definirAlcance', $deOutra->id);
})->throws(ModelNotFoundException::class);

it('não mexe em vínculo que já está ativo', function () {
    $ativo = User::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'ativo@alfa-ps.test']);

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('definirAlcance', $ativo->id);
})->throws(ModelNotFoundException::class);

it('exige users.manage para definir alcance', function () {
    $pendente = ps_pendente($this->tenantA->id, 'pendente@alfa-ps.test');
    $semPermissao = User::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'zé@alfa-ps.test']);

    Livewire::actingAs($semPermissao)
        ->test(ListaUsuarios::class)
        ->call('definirAlcance', $pendente->id)
        ->assertForbidden();

    expect(DB::table('tenant_user')->where('user_id', $pendente->id)->where('tenant_id', $this->tenantA->id)->value('status'))
        ->toBe(User::MEMBERSHIP_PENDING_SCOPE);
});
