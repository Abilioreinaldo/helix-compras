<?php

use App\Enums\StatusUnidade;
use App\Enums\TipoUnidade;
use App\Models\Fornecedor;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Exceptions\TenantMismatchException;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Escopo automático de tenant no Compras — adoção do BelongsToTenant via
 * ComprasModel (base dos modelos de negócio). Uma consulta SEM where('tenant_id')
 * já vem escopada pelo tenant do usuário autenticado: esquecer o filtro vira
 * query escopada, não IDOR cross-tenant. Cura de fundo dos vazamentos da revisão.
 *
 * Multi-tenant: opt-out do contexto canônico global (ver tests/Pest.php) para que
 * as leituras resolvam pelo tenant autenticado, e semeadura explícita por tenant.
 */
beforeEach(fn () => TenantContext::forget());

it('consulta sem filtro já vem escopada pelo tenant do usuário autenticado', function () {
    $tenantA = Tenant::create(['slug' => 'rede-a', 'name' => 'Rede A', 'status' => 'active']);
    $tenantB = Tenant::create(['slug' => 'rede-b', 'name' => 'Rede B', 'status' => 'active']);

    // Semeia um fornecedor em cada tenant (bypass explícito + forceCreate porque
    // tenant_id é system-controlled, fora do fillable).
    Fornecedor::withoutTenantScope()->forceCreate([
        'tenant_id' => $tenantA->id, 'razao_social' => 'Alfa Ltda', 'cnpj' => '11111111000191',
    ]);
    Fornecedor::withoutTenantScope()->forceCreate([
        'tenant_id' => $tenantB->id, 'razao_social' => 'Bravo Ltda', 'cnpj' => '22222222000191',
    ]);

    $this->actingAs(User::factory()->create(['tenant_id' => $tenantA->id]));

    // Sem nenhum where('tenant_id'): o global scope filtra pelo tenant de A.
    expect(Fornecedor::pluck('razao_social')->all())->toBe(['Alfa Ltda'])
        ->and(Fornecedor::withoutTenantScope()->count())->toBe(2); // bypass vê os dois
});

it('carimba tenant_id ao criar dentro de um request autenticado', function () {
    $tenant = Tenant::create(['slug' => 'rede-c', 'name' => 'Rede C', 'status' => 'active']);
    $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]));

    // Sem passar tenant_id: o trait carimba a partir do contexto autenticado.
    $fornecedor = Fornecedor::create(['razao_social' => 'Charlie Ltda', 'cnpj' => '33333333000191']);

    expect($fornecedor->tenant_id)->toBe($tenant->id);
});

it('a mesma chave natural (cnpj) coexiste entre tenants — unique agora é por-tenant', function () {
    $tenantA = Tenant::create(['slug' => 'rede-d', 'name' => 'Rede D', 'status' => 'active']);
    $tenantB = Tenant::create(['slug' => 'rede-e', 'name' => 'Rede E', 'status' => 'active']);

    $cnpj = '44444444000191';
    Fornecedor::withoutTenantScope()->forceCreate(['tenant_id' => $tenantA->id, 'razao_social' => 'D Ltda', 'cnpj' => $cnpj]);

    // Mesmo CNPJ noutro tenant não colide (antes o unique era global).
    Fornecedor::withoutTenantScope()->forceCreate(['tenant_id' => $tenantB->id, 'razao_social' => 'E Ltda', 'cnpj' => $cnpj]);

    expect(Fornecedor::withoutTenantScope()->where('cnpj', $cnpj)->count())->toBe(2);
});

it('não deixa um payload de tela escolher o tenant da unidade (tenant_id fora do fillable)', function () {
    $tenantA = Tenant::create(['slug' => 'rede-f', 'name' => 'Rede F', 'status' => 'active']);
    $tenantB = Tenant::create(['slug' => 'rede-g', 'name' => 'Rede G', 'status' => 'active']);

    $this->actingAs(User::factory()->admin()->create(['tenant_id' => $tenantA->id]));

    // Unidade era a única model de negócio com tenant_id em $fillable: um `tenant_id`
    // injetado no payload de criação chegava ao mass assignment. Hoje a coluna está
    // fora do fillable e, com Model::preventSilentlyDiscardingAttributes() ligado
    // (exigência da fundação v0.2.0), o payload nem é descartado em silêncio: o
    // mass assignment é RECUSADO.
    expect(fn () => Unidade::create([
        'tenant_id' => $tenantB->id,
        'nome' => 'Obra Plantada',
        'tipo' => TipoUnidade::Obra->value,
        'status' => StatusUnidade::Ativa->value,
    ]))->toThrow(MassAssignmentException::class);

    expect(Unidade::withoutTenantScope()->count())->toBe(0);

    // Sem a coluna no payload, quem manda é o carimbo do contexto.
    $unidade = Unidade::create([
        'nome' => 'Obra Plantada',
        'tipo' => TipoUnidade::Obra->value,
        'status' => StatusUnidade::Ativa->value,
    ]);

    expect($unidade->tenant_id)->toBe($tenantA->id)
        ->and(Unidade::withoutTenantScope()->where('tenant_id', $tenantB->id)->count())->toBe(0);

    // E nem um fill() posterior migra o registro (o fill recusa a coluna; e mesmo
    // forçando, a imutabilidade do BelongsToTenant lança no save).
    expect(fn () => $unidade->fill(['tenant_id' => $tenantB->id]))->toThrow(MassAssignmentException::class);
    expect(fn () => $unidade->forceFill(['tenant_id' => $tenantB->id])->save())->toThrow(TenantMismatchException::class);

    expect(Unidade::withoutTenantScope()->whereKey($unidade->id)->value('tenant_id'))->toBe($tenantA->id);
});
