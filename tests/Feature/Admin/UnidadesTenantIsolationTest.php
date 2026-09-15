<?php

use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Isolamento de unidades por tenant: um admin (que vê TODAS as unidades do seu
 * tenant) não pode enxergar unidades de outro tenant. Antes, `unidades` não
 * tinha tenant_id e o UnidadeScope só filtrava por vínculo — o admin via a
 * base inteira.
 */
beforeEach(function () {
    $this->tenantA = Tenant::create(['slug' => 'alpha', 'name' => 'Alpha', 'status' => 'active']);
    $this->adminA = User::factory()->admin()->create([
        'tenant_id' => $this->tenantA->id, 'email' => 'admin@alpha.test',
    ]);

    $this->tenantB = Tenant::create(['slug' => 'bravo', 'name' => 'Bravo', 'status' => 'active']);

    // Modo estrito: gravar noutro tenant exige declarar o tenant-alvo (runFor).
    $this->unidadeA = TenantContext::runFor($this->tenantA->id, fn () => Unidade::factory()->create(['nome' => 'Obra Alpha']));
    $this->unidadeB = TenantContext::runFor($this->tenantB->id, fn () => Unidade::factory()->create(['nome' => 'Obra Bravo']));

    // Multi-tenant: opt-out do contexto canônico global — as leituras devem
    // resolver pelo tenant do usuário autenticado (auth), não pelo fixado.
    TenantContext::forget();
});

it('o admin só enxerga unidades do próprio tenant', function () {
    $this->actingAs($this->adminA);

    $ids = Unidade::query()->pluck('id');

    expect($ids)->toContain($this->unidadeA->id)
        ->and($ids)->not->toContain($this->unidadeB->id);
});

it('mesmo cnpj pode existir em tenants diferentes (unique por tenant)', function () {
    TenantContext::runFor($this->tenantA->id, fn () => Unidade::factory()->create(['cnpj' => '12345678000199']));

    // mesmo CNPJ em outro tenant não colide
    $outra = TenantContext::runFor($this->tenantB->id, fn () => Unidade::factory()->create(['cnpj' => '12345678000199']));

    expect($outra)->not->toBeNull();
});
