<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `unidade_user` ganha `tenant_id` (auditoria multitenant 2026-09-15).
 *
 * O vínculo usuário×unidade×perfil é o que dá acesso operacional (UnidadeScope,
 * AprovacaoPolicy, guards das actions). Sem tenant na pivot, um vínculo era
 * "global": bastava um id de unidade de outro tenant para ganhar perfil nela.
 * Backfill pelo tenant da unidade (fonte de verdade); NOT NULL; unique passa a
 * incluir o tenant. O pivot model (UnidadeUser) carimba a coluna daqui em diante.
 *
 * Compatível com SQLite (testes) e MySQL 8: o change() em SQLite recria a tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidade_user', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        DB::statement(
            'UPDATE unidade_user SET tenant_id = '.
            '(SELECT u.tenant_id FROM unidades u WHERE u.id = unidade_user.unidade_id) '.
            'WHERE tenant_id IS NULL'
        );

        // Vínculo órfão (unidade inexistente) não tem tenant: remove — não há como escopá-lo.
        DB::table('unidade_user')->whereNull('tenant_id')->delete();

        // MySQL/InnoDB: o unique antigo (user_id, ...) pode ser o índice que sustenta a FK
        // de user_id (o índice automático da FK é descartado quando um índice "melhor"
        // aparece). Garante um índice próprio ANTES de derrubar o unique (erro 1553).
        Schema::table('unidade_user', function (Blueprint $table) {
            $table->index('user_id', 'unidade_user_user_idx');
        });

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->dropUnique('unidade_user_user_id_unidade_id_perfil_unique');
        });

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
        });

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->index('tenant_id', 'unidade_user_tenant_idx');
            $table->unique(['tenant_id', 'user_id', 'unidade_id', 'perfil'], 'unidade_user_tenant_user_unidade_perfil_uq');
        });
    }

    public function down(): void
    {
        Schema::table('unidade_user', function (Blueprint $table) {
            $table->dropUnique('unidade_user_tenant_user_unidade_perfil_uq');
            $table->dropIndex('unidade_user_tenant_idx');
        });

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->unique(['user_id', 'unidade_id', 'perfil']);
        });

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->dropIndex('unidade_user_user_idx');
            $table->dropColumn('tenant_id');
        });
    }
};
