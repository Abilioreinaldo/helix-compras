<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_id em `unidades`: sem ele, o UnidadeScope isolava por unidade mas não
 * por tenant — um admin (que vê todas as unidades) enxergava unidades de
 * QUALQUER tenant na base compartilhada de identidade. Backfill pelo tenant dos
 * usuários vinculados (unidade_user → users.tenant_id); fallback no 1º tenant.
 * cnpj passa a ser único POR tenant (era global).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });

        // Backfill: tenant do primeiro usuário vinculado à unidade.
        DB::statement(<<<'SQL'
            UPDATE unidades SET tenant_id = (
                SELECT u.tenant_id
                FROM unidade_user uu
                JOIN users u ON u.id = uu.user_id
                WHERE uu.unidade_id = unidades.id
                LIMIT 1
            )
            WHERE tenant_id IS NULL
        SQL);

        // Unidades órfãs (sem usuário vinculado) → primeiro tenant (base mono-tenant).
        $primeiro = DB::table('tenants')->orderBy('created_at')->value('id');
        if ($primeiro !== null) {
            DB::table('unidades')->whereNull('tenant_id')->update(['tenant_id' => $primeiro]);
        }

        // cnpj único por tenant, não global (a base compartilhada tem vários tenants).
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropUnique('unidades_cnpj_unique');
            $table->unique(['tenant_id', 'cnpj'], 'unidades_tenant_cnpj_uq');
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropUnique('unidades_tenant_cnpj_uq');
            $table->unique('cnpj');
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
