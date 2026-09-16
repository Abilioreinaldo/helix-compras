<?php

use App\Models\Cotacao;
use App\Models\Fornecedor;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * D10 — gate de runbook do onboarding de tenant: o diagnóstico read-only dos
 * mestres precisa passar (--strict) antes de ativar um novo tenant.
 */
it('diagnóstico passa limpo em base sem suspeitas', function () {
    $this->artisan('compras:diagnostico-mestres', ['--strict' => true])->assertExitCode(0);
});

it('mestre sem tenant é suspeita: --strict falha, sem strict só reporta', function () {
    $fornecedor = Fornecedor::factory()->create();
    DB::table('fornecedores')->where('id', $fornecedor->id)->update(['tenant_id' => null]);

    $this->artisan('compras:diagnostico-mestres', ['--strict' => true])->assertExitCode(1);
    $this->artisan('compras:diagnostico-mestres')->assertExitCode(0);
});

it('referência cross-tenant (cotação de um tenant, fornecedor de outro) falha no strict', function () {
    $fornecedor = Fornecedor::factory()->create();
    $cotacao = Cotacao::factory()->create(['fornecedor_id' => $fornecedor->id]);

    // Tenants REAIS: desde a migration de FKs (2026_09_17_000003) um tenant_id que não
    // existe em `tenants` é recusado pelo banco — o cenário a simular aqui é o de duas
    // empresas existentes cruzadas, não o de um id órfão.
    $outroA = Tenant::create(['slug' => 'diag-a', 'name' => 'Diag A', 'status' => 'active']);
    $outroB = Tenant::create(['slug' => 'diag-b', 'name' => 'Diag B', 'status' => 'active']);

    DB::table('fornecedores')->where('id', $fornecedor->id)->update(['tenant_id' => $outroA->id]);
    DB::table('cotacoes')->where('id', $cotacao->id)->update(['tenant_id' => $outroB->id]);

    $this->artisan('compras:diagnostico-mestres', ['--strict' => true])->assertExitCode(1);
});
