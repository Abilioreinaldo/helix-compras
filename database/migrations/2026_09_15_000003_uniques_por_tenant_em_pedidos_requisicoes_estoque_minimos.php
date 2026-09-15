<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaves naturais por TENANT (auditoria multitenant 2026-09-15).
 *
 * Com a numeração agora sequencial por tenant, `PC-2026-0001` e `REQ-2026-000001`
 * passam a existir em vários tenants: os uniques globais colidiriam entre
 * empresas (um tenant "travaria" a numeração do outro). Passam a ser por tenant.
 * `estoque_minimos` idem — identidade (unidade × item) sob o tenant.
 *
 * SQLite: uniques são índices separados (create unique index), então drop/create
 * funciona sem recriar a tabela. MySQL 8: idem via ALTER TABLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_compra', function (Blueprint $table) {
            $table->dropUnique('pedidos_compra_numero_unique');
            $table->dropUnique('pedidos_compra_ano_sequencia_unique');
        });

        Schema::table('pedidos_compra', function (Blueprint $table) {
            $table->unique(['tenant_id', 'numero'], 'pedidos_compra_tenant_numero_uq');
            $table->unique(['tenant_id', 'ano', 'sequencia'], 'pedidos_compra_tenant_ano_seq_uq');
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropUnique('requisicoes_codigo_unique');
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->unique(['tenant_id', 'codigo'], 'requisicoes_tenant_codigo_uq');
        });

        // MySQL/InnoDB: o unique (unidade_id, item_catalogo_id) sustenta a FK de unidade_id
        // (o índice automático da FK é descartado quando um índice "melhor" aparece).
        // Índice próprio ANTES de derrubar o unique — senão erro 1553.
        Schema::table('estoque_minimos', function (Blueprint $table) {
            $table->index('unidade_id', 'estoque_minimos_unidade_idx');
        });

        Schema::table('estoque_minimos', function (Blueprint $table) {
            $table->dropUnique('estoque_minimos_identidade_unique');
        });

        Schema::table('estoque_minimos', function (Blueprint $table) {
            $table->unique(['tenant_id', 'unidade_id', 'item_catalogo_id'], 'estoque_minimos_tenant_identidade_uq');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_minimos', function (Blueprint $table) {
            $table->dropUnique('estoque_minimos_tenant_identidade_uq');
        });
        Schema::table('estoque_minimos', function (Blueprint $table) {
            $table->unique(['unidade_id', 'item_catalogo_id'], 'estoque_minimos_identidade_unique');
        });
        Schema::table('estoque_minimos', function (Blueprint $table) {
            $table->dropIndex('estoque_minimos_unidade_idx');
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropUnique('requisicoes_tenant_codigo_uq');
        });
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->unique('codigo');
        });

        Schema::table('pedidos_compra', function (Blueprint $table) {
            $table->dropUnique('pedidos_compra_tenant_numero_uq');
            $table->dropUnique('pedidos_compra_tenant_ano_seq_uq');
        });
        Schema::table('pedidos_compra', function (Blueprint $table) {
            $table->unique('numero');
            $table->unique(['ano', 'sequencia']);
        });
    }
};
