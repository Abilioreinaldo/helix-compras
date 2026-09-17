<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4ª auditoria adversarial — COMPRAS-5: FK COMPOSTA para a tabela mãe.
 *
 * As sondas P4-L6 e P4-U6 mostraram que o banco ACEITAVA linha cruzada entre tenants:
 *  - `cotacao_links` carimbado no tenant B apontando para cotação (ou fornecedor) do A;
 *  - `unidade_user` carimbado no tenant A apontando para unidade do B.
 * A aplicação barrava (escopo de tenant + policy), mas era a ÚNICA camada. A FK simples
 * `cotacao_id → cotacoes(id)` não olha tenant; a FK composta de `unidade_user` criada em
 * 2026_09_21_000002 cobre só (user_id, tenant_id) → tenant_user, não a unidade.
 *
 * Esta migration cria:
 *  - unique (id, tenant_id) em `cotacoes`, `fornecedores` e `unidades` (índice exigido
 *    pelo MySQL/SQLite para ser alvo de FK composta — mesmo desenho da fundação,
 *    2026_09_18_000002);
 *  - `cotacao_links (cotacao_id, tenant_id)    → cotacoes (id, tenant_id)`     CASCADE
 *  - `cotacao_links (fornecedor_id, tenant_id) → fornecedores (id, tenant_id)` RESTRICT
 *  - `unidade_user  (unidade_id, tenant_id)    → unidades (id, tenant_id)`     CASCADE
 * (mesmo ON DELETE das FKs simples existentes, que continuam lá).
 *
 * SANEAMENTO: NÃO corrige dados. Se houver linha CRUZADA (tenant da filha ≠ tenant da
 * mãe), ABORTA com a contagem e a query de diagnóstico — nada é alterado. A reconciliação
 * é do DBA (`php artisan helix:integridade-tenant`, docs/SANEAMENTO-TENANT.md).
 *
 * Idempotente e com down(). SQLite recria a tabela ao adicionar FK (nenhuma das duas tem
 * índice parcial); MySQL 8 usa o unique (id, tenant_id) da mãe como índice referenciado.
 */
return new class extends Migration
{
    /** @var list<array{filha: string, coluna: string, mae: string, on_delete: string}> */
    private const FKS = [
        ['filha' => 'cotacao_links', 'coluna' => 'cotacao_id', 'mae' => 'cotacoes', 'on_delete' => 'cascade'],
        ['filha' => 'cotacao_links', 'coluna' => 'fornecedor_id', 'mae' => 'fornecedores', 'on_delete' => 'restrict'],
        ['filha' => 'unidade_user', 'coluna' => 'unidade_id', 'mae' => 'unidades', 'on_delete' => 'cascade'],
    ];

    public function up(): void
    {
        $pendentes = array_values(array_filter(self::FKS, fn (array $fk) => ! $this->temFkComposta($fk)));

        if ($pendentes === []) {
            return;
        }

        $this->recusarLinhasCruzadas($pendentes);

        foreach ($pendentes as $fk) {
            if (! $this->temUniqueIdTenant($fk['mae'])) {
                Schema::table($fk['mae'], fn (Blueprint $table) => $table->unique(['id', 'tenant_id'], $this->nomeUnique($fk['mae'])));
            }

            Schema::table($fk['filha'], function (Blueprint $table) use ($fk) {
                $foreign = $table->foreign([$fk['coluna'], 'tenant_id'])
                    ->references(['id', 'tenant_id'])
                    ->on($fk['mae']);

                $fk['on_delete'] === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::FKS as $fk) {
            if ($this->temFkComposta($fk)) {
                // Por COLUNAS (nome convencional): o SQLite não derruba FK por nome.
                Schema::table($fk['filha'], fn (Blueprint $table) => $table->dropForeign([$fk['coluna'], 'tenant_id']));
            }
        }

        foreach (array_unique(array_column(self::FKS, 'mae')) as $mae) {
            if ($this->temUniqueIdTenant($mae)) {
                Schema::table($mae, fn (Blueprint $table) => $table->dropUnique($this->nomeUnique($mae)));
            }
        }
    }

    /**
     * @param  list<array{filha: string, coluna: string, mae: string, on_delete: string}>  $fks
     */
    private function recusarLinhasCruzadas(array $fks): void
    {
        $apelidos = ['cotacao_links' => 'cl', 'unidade_user' => 'uu'];
        $bloqueios = [];

        foreach ($fks as $fk) {
            $cruzadas = DB::table($fk['filha'].' as f')
                ->join($fk['mae'].' as m', 'm.id', '=', 'f.'.$fk['coluna'])
                ->where(fn ($q) => $q->whereColumn('m.tenant_id', '<>', 'f.tenant_id')->orWhereNull('m.tenant_id')->orWhereNull('f.tenant_id'))
                ->count();

            if ($cruzadas > 0) {
                $a = $apelidos[$fk['filha']];
                $bloqueios[] = "{$cruzadas} linha(s) CRUZADA(S) em {$fk['filha']}.{$fk['coluna']} — tenant da linha diferente do tenant de {$fk['mae']}:\n"
                    ."   select {$a}.*, m.tenant_id as tenant_da_mae from {$fk['filha']} {$a} join {$fk['mae']} m on m.id = {$a}.{$fk['coluna']} "
                    ."where m.tenant_id <> {$a}.tenant_id or m.tenant_id is null or {$a}.tenant_id is null;";
            }
        }

        if ($bloqueios !== []) {
            throw new RuntimeException(
                "FK composta para a tabela mãe NÃO criada: há linhas cruzadas entre tenants.\n - "
                .implode("\n - ", $bloqueios)
                ."\nNada foi alterado. Reconcilie (corrija o tenant ou remova a linha) e rode a migration de novo. "
                .'Relatório completo: php artisan helix:integridade-tenant.'
            );
        }
    }

    /** @param  array{filha: string, coluna: string, mae: string, on_delete: string}  $fk */
    private function temFkComposta(array $fk): bool
    {
        foreach (Schema::getForeignKeys($fk['filha']) as $existente) {
            if ($existente['columns'] === [$fk['coluna'], 'tenant_id'] && $existente['foreign_table'] === $fk['mae']) {
                return true;
            }
        }

        return false;
    }

    private function temUniqueIdTenant(string $mae): bool
    {
        foreach (Schema::getIndexes($mae) as $indice) {
            if ($indice['unique'] && $indice['columns'] === ['id', 'tenant_id']) {
                return true;
            }
        }

        return false;
    }

    private function nomeUnique(string $mae): string
    {
        return $mae.'_id_tenant_id_unique';
    }
};
