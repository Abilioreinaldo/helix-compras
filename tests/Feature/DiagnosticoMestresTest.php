<?php

use App\Models\Cotacao;
use App\Models\Fornecedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    DB::table('fornecedores')->where('id', $fornecedor->id)->update(['tenant_id' => (string) Str::uuid()]);
    DB::table('cotacoes')->where('id', $cotacao->id)->update(['tenant_id' => (string) Str::uuid()]);

    $this->artisan('compras:diagnostico-mestres', ['--strict' => true])->assertExitCode(1);
});
