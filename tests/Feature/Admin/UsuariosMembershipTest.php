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
 * Identidade COMPARTILHADA na tela de usuários (auditoria adversarial, achado ALTO).
 *
 * `users.tenant_id` é só o tenant HOME da identidade; quem participa de uma empresa
 * é definido pela MEMBERSHIP (pivot `tenant_user`). Filtrar a tela pelo home errava
 * nas duas direções — e a operação errada não era só "ver a mais": inativar/excluir
 * mexem na IDENTIDADE e derrubavam o acesso do usuário nas OUTRAS empresas.
 *
 * Provas de negativa abaixo: o ataque (admin de A colateralizando o tenant B) falha.
 */
beforeEach(function () {
    $this->tenantA = Tenant::create(['slug' => 'alfa-ms', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-ms', 'name' => 'Bravo', 'status' => 'active']);

    $this->adminA = User::factory()->admin()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Admin Alfa', 'email' => 'admin@alfa.test',
    ]);

    // Multi-tenant: as leituras resolvem pelo tenant do autenticado, não pelo canônico.
    TenantContext::forget();
});

/** Concede (ou revoga) a membership de $user em $tenant. */
function vinculo(User $user, string $tenantId, string $status = 'active', bool $isAdmin = false): void
{
    $user->memberships()->syncWithoutDetaching([$tenantId => ['is_admin' => $isAdmin, 'status' => $status, 'access_scope' => 'corporate']]);
}

// ─── Leitura por membership ──────────────────────────────────────────────────

it('lista o membro convidado cuja identidade mora em outro tenant', function () {
    // Home em B, trabalha em A: era INVISÍVEL para o admin de A.
    $convidado = User::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Convidado Bravo']);
    vinculo($convidado, $this->tenantA->id);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->assertSee('Convidado Bravo');
});

it('não lista mais quem teve o vínculo com esta empresa revogado', function () {
    // Home em A (a coluna users.tenant_id continua apontando para cá), mas sem
    // membership ativa: não é mais gente desta empresa e não pode ser administrado.
    $exMembro = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Ex Membro']);
    vinculo($exMembro, $this->tenantA->id, status: 'inactive');

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->assertDontSee('Ex Membro');

    expect(fn () => Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('excluir', $exMembro->id))
        ->toThrow(ModelNotFoundException::class);
});

// ─── v0.7.0: vínculo SUSPENSO é visível e revogável (membersOf com status) ────

it('lista quem está SUSPENSO nesta empresa e permite revogar o vínculo', function () {
    // Até a v0.6.x `membersOf()` só trazia vínculo ATIVO: quem estava suspenso sumia da
    // tela — e o `excluir` respondia 404. O admin ficava sem ver (e sem poder fechar)
    // uma porta que continua aberta para a empresa dele. A v0.7.0 deu status ao
    // `membersOf`, e a listagem passou a enxergar o suspenso.
    $suspenso = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Membro Suspenso']);
    vinculo($suspenso, $this->tenantA->id, status: 'suspended');

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->assertSee('Membro Suspenso')
        ->assertSee('Vínculo suspenso');

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('excluir', $suspenso->id)
        ->assertOk();

    expect(DB::table('tenant_user')->where('user_id', $suspenso->id)->where('tenant_id', $this->tenantA->id)->exists())->toBeFalse();
});

it('vínculo suspenso não abre a edição (reativar não é ação desta tela)', function () {
    $suspenso = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Membro Suspenso']);
    vinculo($suspenso, $this->tenantA->id, status: 'suspended');

    // Fail-closed: as ações que operam sobre vínculo ATIVO continuam resolvendo pela
    // consulta de ativos — ver o suspenso na lista não o torna editável.
    expect(fn () => Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $suspenso->id))
        ->toThrow(ModelNotFoundException::class);
});

it('não mostra os papéis que o convidado tem na empresa dele', function () {
    $convidado = User::factory()->compradora()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Convidado Compras',
    ]);
    vinculo($convidado, $this->tenantA->id);

    // O papel `compras` dele é do tenant B (pivot user_role.tenant_id = B).
    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->assertSee('Convidado Compras')
        ->assertSee('Sem papel');
});

// ─── Negativa: a operação do admin de A não atravessa para B ─────────────────

it('excluir um usuário que também participa de outra empresa não apaga a identidade nem o acesso lá', function () {
    $compartilhado = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Compartilhado']);
    vinculo($compartilhado, $this->tenantB->id);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('excluir', $compartilhado->id);

    // A identidade sobrevive e o vínculo com B continua ativo…
    expect(User::withTrashed()->find($compartilhado->id)?->deleted_at)->toBeNull()
        ->and(DB::table('tenant_user')->where('user_id', $compartilhado->id)
            ->where('tenant_id', $this->tenantB->id)->where('status', 'active')->exists())->toBeTrue()
        // …e só o vínculo com A caiu.
        ->and(DB::table('tenant_user')->where('user_id', $compartilhado->id)
            ->where('tenant_id', $this->tenantA->id)->exists())->toBeFalse();
});

it('não inativa globalmente um usuário que também participa de outra empresa', function () {
    $compartilhado = User::factory()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Compartilhado', 'email' => 'comp@alfa.test',
    ]);
    vinculo($compartilhado, $this->tenantB->id);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $compartilhado->id)
        ->set('status', 'inactive')
        ->call('salvar')
        ->assertHasErrors('status');

    // users.status é da IDENTIDADE: continuar ativo é o que preserva o acesso em B.
    expect($compartilhado->fresh()->status)->toBe('active');
});

it('não deixa o admin editar a identidade de um convidado de outra empresa', function () {
    $convidado = User::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Convidado Bravo']);
    vinculo($convidado, $this->tenantA->id);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $convidado->id)
        ->assertForbidden();

    expect($convidado->fresh()->name)->toBe('Convidado Bravo');
});

// ─── O caminho normal segue igual ────────────────────────────────────────────

it('excluir usuário de vínculo único segue removendo a identidade', function () {
    $soDaqui = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'So Daqui']);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('excluir', $soDaqui->id);

    expect(User::find($soDaqui->id))->toBeNull();
});

it('inativar usuário de vínculo único segue funcionando', function () {
    $soDaqui = User::factory()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'So Daqui', 'email' => 'so@alfa.test',
    ]);

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $soDaqui->id)
        ->set('status', 'inactive')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($soDaqui->fresh()->status)->toBe('inactive');
});
