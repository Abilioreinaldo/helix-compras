<?php

use App\Enums\Perfil;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Mail\TenantInvitationMail;
use Helix\Foundation\Models\Platform\Identity\Permission;
use Helix\Foundation\Models\Platform\Identity\Role;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Identity\EntitlementService;
use Helix\Foundation\Services\Platform\Identity\InvitationService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    // Fundação v0.7.0 (FUNDACAO-8): permissão é sempre NUM tenant. Este teste é
    // multi-tenant (fez `TenantContext::forget()` no setup), então declara o tenant da
    // pergunta — fora de contexto, `temPerfil`/`podeVer*` NEGAM, por desenho.
    expect($compradora->temPerfilEm(Perfil::CompradoraSenior, $this->tenant->id))->toBeTrue()
        ->and($compradora->temPerfil(Perfil::CompradoraSenior))->toBeFalse() // sem tenant no contexto: nega
        ->and($compradora->hasPermissionIn($this->tenant->id, 'compras.manage'))->toBeTrue()
        ->and($compradora->hasPermissionIn($this->tenant->id, 'pagamentos.manage'))->toBeFalse()
        ->and($financeiro->hasPermissionIn($this->tenant->id, 'pagamentos.manage'))->toBeTrue()
        ->and($financeiro->hasPermissionIn($this->tenant->id, 'compras.manage'))->toBeFalse()
        ->and($financeiro->hasPermissionIn($this->tenant->id, 'compras.view'))->toBeTrue();

    // E, com o tenant declarado, os atalhos do app respondem o mesmo.
    TenantContext::runFor($this->tenant->id, function () use ($compradora, $financeiro) {
        expect($compradora->podeVerTodasUnidades())->toBeTrue()
            ->and($compradora->podeVerPagamentos())->toBeFalse()
            ->and($financeiro->podeVerPagamentos())->toBeTrue()
            ->and($financeiro->podeVerTodasUnidades())->toBeFalse()
            ->and($financeiro->isComprasStaff())->toBeTrue();
    });

    // O admin tira compras.manage do papel → a compradora perde a visão global na hora.
    // Role/Permission são escopados (BelongsToTenant): no modo estrito o setup declara o tenant.
    [$role, $manage] = TenantContext::runFor($this->tenant->id, fn () => [
        Role::where('tenant_id', $this->tenant->id)->where('slug', 'compras')->firstOrFail(),
        Permission::where('tenant_id', $this->tenant->id)->where('slug', 'compras.manage')->firstOrFail(),
    ]);
    $role->permissions()->detach($manage->id);

    TenantContext::runFor($this->tenant->id, function () use ($compradora) {
        expect($compradora->fresh()->podeVerTodasUnidades())->toBeFalse()
            ->and($compradora->fresh()->can('compras.manage'))->toBeFalse();
    });
});

it('tela de usuários atribui papéis do catálogo pelo convite e pelo UserService', function () {
    Mail::fake();
    User::factory()->compradora()->create(['tenant_id' => $this->tenant->id]); // semeia o RBAC do tenant
    $financeiro = TenantContext::runFor($this->tenant->id, fn () => Role::where('tenant_id', $this->tenant->id)->where('slug', 'financeiro')->firstOrFail());

    // v0.5.0: criar virou CONVITE — os papéis viajam no convite e valem no aceite.
    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('email', 'paulo@alpha.test')
        ->set('papeis', [$financeiro->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $url = null;
    Mail::assertQueued(TenantInvitationMail::class, function (TenantInvitationMail $mail) use (&$url) {
        $url = $mail->url;

        return $mail->hasTo('paulo@alpha.test');
    });

    TenantContext::forget();
    app(InvitationService::class)->acceptAsNewUser(
        basename(parse_url((string) $url, PHP_URL_PATH)),
        'Paulo Financeiro',
        'S3nha-Da-Propria-Pessoa!',
    );
    TenantContext::set($this->tenant->id);

    $paulo = User::where('email', 'paulo@alpha.test')->firstOrFail();

    expect($paulo->hasRole('financeiro'))->toBeTrue()
        ->and($paulo->podeVerPagamentos())->toBeTrue()
        ->and($paulo->precisa_trocar_senha)->toBeFalse()
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
