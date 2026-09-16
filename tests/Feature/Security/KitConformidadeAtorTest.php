<?php

use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O ator autenticado pelo kit de conformidade (auditoria adversarial, achado 6).
 *
 * O `beforeEach` do HelixConformanceTest autenticava um SUPERADMIN. Um superadmin
 * satisfaz `hasPermission()` por atalho (logo o Gate::before libera qualquer
 * verificação) e transcende tenants — os 19 testes do kit rodavam num mundo onde
 * nada é negado. Trocado pela compradora sênior, que chega às unidades pela
 * permissão de verdade. Estes testes fixam a diferença para que a regressão
 * (voltar a um ator privilegiado) apareça.
 */
it('o ator do kit não passa por cima da autorização como o superadmin passava', function () {
    $atorDoKit = User::factory()->compradora()->create();
    $superadmin = User::factory()->create(['is_superadmin' => true]);

    // `users.manage` não está em nenhum papel do Compras (é do admin do tenant).
    expect($superadmin->can('users.manage'))->toBeTrue()   // o atalho que tornava o kit inerte
        ->and($atorDoKit->can('users.manage'))->toBeFalse() // o ator atual é negado de verdade
        ->and($atorDoKit->isSuperadmin())->toBeFalse()
        ->and($atorDoKit->isAdmin())->toBeFalse();
});

it('o ator do kit alcança as unidades pela permissão, não por bypass do UnidadeScope', function () {
    Unidade::factory()->count(3)->create();

    $atorDoKit = User::factory()->compradora()->create();
    $semPermissao = User::factory()->create(); // sem papel e sem vínculo de unidade

    // Compradora: compras.manage ⇒ toda a rede do tenant (o scope não acrescenta filtro).
    $this->actingAs($atorDoKit);
    expect(Unidade::query()->count())->toBe(3);

    // Sem permissão e sem vínculo: o UnidadeScope continua VIVO e falha fechado.
    $this->actingAs($semPermissao);
    expect(Unidade::query()->count())->toBe(0);
});
