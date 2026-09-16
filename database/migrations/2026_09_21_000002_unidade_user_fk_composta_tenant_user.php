<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decisão 17 (parecer DBA MySQL): `unidade_user(user_id, tenant_id)` →
 * `tenant_user(user_id, tenant_id)` ON DELETE CASCADE — o mesmo desenho da
 * `user_role` da fundação (2026_09_19_000001).
 *
 * O vínculo usuário×unidade×perfil é o que dá acesso operacional (UnidadeScope,
 * AprovacaoPolicy, guards das actions). Sem a FK, remover a membership do usuário
 * na empresa deixava o vínculo com a unidade vivo — um acesso que "volta sozinho" no
 * dia em que a membership for recriada. Com a FK composta, o banco:
 *  - recusa vínculo com unidade para quem não é membro daquele tenant;
 *  - apaga os vínculos do tenant quando a membership sai (CASCADE).
 *
 * SANEAMENTO: esta migration NÃO corrige dados. Se houver linha ÓRFÃ (sem membership
 * no tenant do vínculo) ou CRUZADA (tenant do vínculo ≠ tenant da unidade), ela
 * ABORTA com a contagem e a query de diagnóstico — a reconciliação é do DBA
 * (relatório `php artisan helix:integridade-tenant`, docs/SANEAMENTO-TENANT.md).
 *
 * Colunas de AUTORIA (solicitante_id, criada_por, ...) NÃO ganham FK para
 * tenant_user de propósito: o autor pode deixar a empresa e o registro histórico
 * tem de continuar existindo. Elas seguem com FK simples para `users` e a membership
 * ativa é validada na gravação.
 *
 * Idempotente e com down(). SQLite recria a tabela ao adicionar a FK (sem índices
 * parciais nesta tabela, nada se perde); MySQL 8 usa o PK (user_id, tenant_id) de
 * tenant_user como índice referenciado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->temFkComposta()) {
            return;
        }

        $this->recusarInconsistencias();

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->foreign(['user_id', 'tenant_id'])
                ->references(['user_id', 'tenant_id'])
                ->on('tenant_user')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->temFkComposta()) {
            return;
        }

        Schema::table('unidade_user', function (Blueprint $table) {
            $table->dropForeign(['user_id', 'tenant_id']);
        });
    }

    private function recusarInconsistencias(): void
    {
        $orfas = DB::table('unidade_user as uu')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tenant_user as tu')
                ->whereColumn('tu.user_id', 'uu.user_id')
                ->whereColumn('tu.tenant_id', 'uu.tenant_id'))
            ->count();

        $cruzadas = DB::table('unidade_user as uu')
            ->join('unidades as un', 'un.id', '=', 'uu.unidade_id')
            ->whereColumn('un.tenant_id', '<>', 'uu.tenant_id')
            ->count();

        $bloqueios = [];

        if ($orfas > 0) {
            $bloqueios[] = "{$orfas} vínculo(s) ÓRFÃO(S) — usuário sem membership no tenant do vínculo:\n"
                .'   select uu.* from unidade_user uu where not exists (select 1 from tenant_user tu '
                .'where tu.user_id = uu.user_id and tu.tenant_id = uu.tenant_id);';
        }

        if ($cruzadas > 0) {
            $bloqueios[] = "{$cruzadas} vínculo(s) CRUZADO(S) — tenant do vínculo diferente do tenant da unidade:\n"
                .'   select uu.*, un.tenant_id as tenant_da_unidade from unidade_user uu '
                .'join unidades un on un.id = uu.unidade_id where un.tenant_id <> uu.tenant_id;';
        }

        if ($bloqueios !== []) {
            throw new RuntimeException(
                "FK composta unidade_user → tenant_user NÃO criada: há vínculos inconsistentes.\n - "
                .implode("\n - ", $bloqueios)
                ."\nNada foi alterado. Reconcilie (recrie a membership, corrija o tenant ou remova o vínculo) "
                .'e rode a migration de novo. Relatório completo: php artisan helix:integridade-tenant.'
            );
        }
    }

    private function temFkComposta(): bool
    {
        foreach (Schema::getForeignKeys('unidade_user') as $fk) {
            if ($fk['columns'] === ['user_id', 'tenant_id'] && $fk['foreign_table'] === 'tenant_user') {
                return true;
            }
        }

        return false;
    }
};
