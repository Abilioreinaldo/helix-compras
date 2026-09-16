<?php

use App\Enums\Perfil;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Permission;
use Helix\Foundation\Models\Platform\Identity\Role;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Identity\EntitlementService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Papéis globais do Compras autorizam por PERMISSÃO (governada pelo admin da
 * empresa em Papéis & Permissões), e a tela de usuários atribui os papéis do
 * catálogo — não mais um checkbox "Compradora" hardcoded.
 */
beforeEach(function () {
    // Teste multi-tenant (tenant próprio): opt-out do contexto canônico global —
    // Role/Permission da fundação são escopados pelo BelongsToTenant.
    TenantContext::forget();

    $this->tenant = Tenant::create(['slug' => 'alpha', 'name' => 'Alpha', 'status' => 'active']);
    // v0.4.0: ligar feature é operação de PLATAFORMA (o teste faz o papel do console).
    TenantContext::runAsPlatform(fn () => app(EntitlementService::class)->enable($this->tenant, 'compras'));
    $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
});

it('compradora e financeiro são graduados por permissão, não por slug', function () {
    $compradora = User::factory()->compradora()->create(['tenant_id' => $this->tenant->id]);
    $financeiro = User::factory()->financeiro()->create(['tenant_id' => $this->tenant->id]);

    expect($compradora->temPerfil(Perfil::CompradoraSenior))->toBeTrue()
        ->and($compradora->podeVerTodasUnidades())->toBeTrue()
        ->and($compradora->podeVerPagamentos())->toBeFalse()
        ->and($financeiro->podeVerPagamentos())->toBeTrue()
        ->and($financeiro->podeVerTodasUnidades())->toBeFalse()
        ->and($financeiro->isComprasStaff())->toBeTrue();

    // O admin tira compras.manage do papel → a compradora perde a visão global na hora.
    // Role/Permission são escopados (BelongsToTenant): no modo estrito o setup declara o tenant.
    [$role, $manage] = TenantContext::runFor($this->tenant->id, fn () => [
        Role::where('tenant_id', $this->tenant->id)->where('slug', 'compras')->firstOrFail(),
        Permission::where('tenant_id', $this->tenant->id)->where('slug', 'compras.manage')->firstOrFail(),
    ]);
    $role->permissions()->detach($manage->id);

    expect($compradora->fresh()->podeVerTodasUnidades())->toBeFalse()
        ->and($compradora->fresh()->can('compras.manage'))->toBeFalse();
});

it('tela de usuários atribui papéis do catálogo e passa pelo UserService', function () {
    User::factory()->compradora()->create(['tenant_id' => $this->tenant->id]); // semeia o RBAC do tenant
    $financeiro = TenantContext::runFor($this->tenant->id, fn () => Role::where('tenant_id', $this->tenant->id)->where('slug', 'financeiro')->firstOrFail());

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('name', 'Paulo Financeiro')
        ->set('email', 'paulo@alpha.test')
        ->set('papeis', [$financeiro->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $paulo = User::where('email', 'paulo@alpha.test')->firstOrFail();

    expect($paulo->hasRole('financeiro'))->toBeTrue()
        ->and($paulo->podeVerPagamentos())->toBeTrue()
        ->and($paulo->precisa_trocar_senha)->toBeTrue()
        ->and($paulo->belongsToTenant($this->tenant->id))->toBeTrue();

    // Editar: remover o papel.
    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $paulo->id)
        ->assertSet('papeis', [(string) $financeiro->id])
        ->set('papeis', [])
        ->call('salvar')
        ->assertHasNoErrors();

    expect($paulo->fresh()->hasRole('financeiro'))->toBeFalse();
});

it('recusa papel de outro tenant', function () {
    $outro = Tenant::create(['slug' => 'bravo', 'name' => 'Bravo', 'status' => 'active']);
    // v0.2.0: tenant_id saiu do $fillable — quem carimba é o BelongsToTenant, a
    // partir do contexto. Criar "no outro tenant" é declarado com runFor.
    $alheio = TenantContext::runFor((string) $outro->id, fn () => Role::create(['slug' => 'compras', 'name' => 'Compras']));

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('name', 'X')
        ->set('email', 'x@alpha.test')
        ->set('papeis', [$alheio->id])
        ->call('salvar')
        ->assertHasErrors('papeis.0');
});

it('Papéis & Permissões fica dentro do Compras, só para o admin da empresa', function () {
    $compradora = User::factory()->compradora()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->get('/admin/papeis')->assertOk()->assertSee('Compradora sênior');
    $this->actingAs($compradora)->get('/admin/papeis')->assertForbidden();
});
