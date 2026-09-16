<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chaves naturais que só eram por-tenant POR TRANSITIVIDADE (2ª auditoria adversarial).
 *
 * Três uniques continuavam GLOBAIS na instalação:
 *   1. `pagamentos (pedido_compra_id) WHERE deleted_at IS NULL`
 *   2. `cotacoes (requisicao_id, fornecedor_id, deleted_at)`
 *   3. `saldos_estoque (unidade_id, deposito, item_catalogo_id) WHERE ...`
 *
 * Elas "não colidem entre tenants" só porque a FK da esquerda (pedido, requisição,
 * unidade) pertence a um tenant — uma garantia que o BANCO não faz: nada impede uma
 * linha de A apontar para o pedido/requisição/unidade de B, nem a FK cruzada de
 * existir por um bug, um import ou um restore parcial. Bastava UMA linha assim para
 * o outro tenant ficar PERMANENTEMENTE impedido de fazer a operação legítima — e ele
 * não teria como sequer enxergar o que o bloqueia. `tenant_id` na chave transforma a
 * garantia transitiva em garantia do banco.
 *
 * Aditiva e idempotente (roda sobre base já migrada); `down()` devolve os índices
 * antigos. Driver-aware, como as migrations que criaram estes índices:
 *  - SQLite: índice ÚNICO PARCIAL nativo (CREATE UNIQUE INDEX ... WHERE ...);
 *  - MySQL: não tem índice parcial — o unique vai sobre a COLUNA GERADA que já existe
 *    (`pedido_ativo_key`, `catalogo_chave_unica`), com `tenant_id` na frente. Fora do
 *    escopo do índice a coluna gerada é NULL, e NULLs não colidem — mesma semântica.
 *
 * `tenant_id NOT NULL`: AVALIADO e deliberadamente NÃO aplicado aqui. Numa chave com
 * NULL o unique nunca dispara (NULL é distinto em índice único nos dois bancos), então
 * a coluna nula seria um furo no índice novo — mas em SQLite mudar a nulidade
 * RECONSTRÓI a tabela, e é exatamente essa reconstrução que já apagou o `WHERE` dos
 * índices parciais deste schema uma vez (ver 2026_09_17_000003). O risco de trocar um
 * furo estreito por um índice parcial silenciosamente virando total não compensa. No
 * lugar disso: a FK `tenant_id → tenants` (000003) já existe, o carimbo é automático
 * (BelongsToTenant, `enforce_stamp`), e a migration ABORTA abaixo se encontrar órfão —
 * que é o único jeito de a coluna ficar nula hoje.
 */
return new class extends Migration
{
    /** @var array<int, string> tabelas cujas chaves naturais passam a ter tenant_id */
    private array $tabelas = ['pagamentos', 'cotacoes', 'saldos_estoque'];

    public function up(): void
    {
        $this->recusarOrfaos();

        $sqlite = DB::getDriverName() === 'sqlite';

        $this->pagamentos($sqlite);
        $this->cotacoes();
        $this->saldosEstoque($sqlite);
    }

    public function down(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        // saldos_estoque
        if (Schema::hasIndex('saldos_estoque', 'saldos_estoque_tenant_catalogo_uq')) {
            $this->dropIndice('saldos_estoque', 'saldos_estoque_tenant_catalogo_uq');
        }
        if (! Schema::hasIndex('saldos_estoque', 'saldos_estoque_catalogo_unique')) {
            DB::statement($sqlite
                ? 'CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque '
                    .'(unidade_id, deposito, item_catalogo_id) WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL'
                : 'CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque (catalogo_chave_unica)');
        }

        // cotacoes
        if (Schema::hasIndex('cotacoes', 'cotacoes_tenant_req_fornecedor_uq')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropUnique('cotacoes_tenant_req_fornecedor_uq');
            });
        }
        if (! Schema::hasIndex('cotacoes', 'cotacoes_requisicao_id_fornecedor_id_deleted_at_unique')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->unique(['requisicao_id', 'fornecedor_id', 'deleted_at']);
            });
        }
        if (Schema::hasIndex('cotacoes', 'cotacoes_requisicao_id_index')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropIndex('cotacoes_requisicao_id_index');
            });
        }

        // pagamentos
        if (Schema::hasIndex('pagamentos', 'pagamentos_tenant_pedido_ativo_uq')) {
            $this->dropIndice('pagamentos', 'pagamentos_tenant_pedido_ativo_uq');
        }
        if (! Schema::hasIndex('pagamentos', 'pagamentos_pedido_ativo_unique')) {
            DB::statement($sqlite
                ? 'CREATE UNIQUE INDEX pagamentos_pedido_ativo_unique ON pagamentos (pedido_compra_id) WHERE deleted_at IS NULL'
                : 'CREATE UNIQUE INDEX pagamentos_pedido_ativo_unique ON pagamentos (pedido_ativo_key)');
        }
    }

    /**
     * Uma linha sem tenant só pode ter vindo do saneamento de órfão da 000003 (que
     * zera o `tenant_id` que não casa com nenhum tenant). Com ela na base, a chave
     * nova teria um NULL e o unique não dispararia para aquela linha: melhor travar
     * o deploy e mostrar o problema do que criar um índice que mente.
     */
    private function recusarOrfaos(): void
    {
        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'tenant_id')) {
                continue;
            }

            $orfas = DB::table($tabela)->whereNull('tenant_id')->count();

            if ($orfas > 0) {
                throw new RuntimeException(
                    "A tabela `{$tabela}` tem {$orfas} linha(s) com tenant_id NULL. A chave natural por tenant "
                    .'não pode ser criada sobre elas (NULL nunca colide num índice único, e a linha ficaria fora '
                    .'da garantia). Atribua o tenant correto a essas linhas — ou remova-as — e rode a migration de novo.'
                );
            }
        }
    }

    /** 1 pagamento ATIVO por pedido — POR TENANT. */
    private function pagamentos(bool $sqlite): void
    {
        if (Schema::hasIndex('pagamentos', 'pagamentos_tenant_pedido_ativo_uq')) {
            return;
        }

        if (Schema::hasIndex('pagamentos', 'pagamentos_pedido_ativo_unique')) {
            $this->dropIndice('pagamentos', 'pagamentos_pedido_ativo_unique');
        }

        DB::statement($sqlite
            // Índice parcial nativo: só linhas vivas entram na chave.
            ? 'CREATE UNIQUE INDEX pagamentos_tenant_pedido_ativo_uq ON pagamentos (tenant_id, pedido_compra_id) WHERE deleted_at IS NULL'
            // `pedido_ativo_key` já é NULL quando a linha está soft-deletada (coluna
            // gerada criada em 2026_06_22_210006) — reaproveitada com tenant_id na frente.
            : 'CREATE UNIQUE INDEX pagamentos_tenant_pedido_ativo_uq ON pagamentos (tenant_id, pedido_ativo_key)');
    }

    /**
     * Uma cotação por (requisição, fornecedor) — POR TENANT.
     *
     * A forma da chave (com `deleted_at` na ponta) é preservada de propósito: mudá-la
     * para um parcial "só ativas" ENDURECERIA a regra (hoje, como NULL é distinto, duas
     * cotações ativas do mesmo fornecedor na mesma requisição passam) e isso é decisão
     * de produto, não de migração de índice. Fica como observação para o backlog.
     */
    private function cotacoes(): void
    {
        if (Schema::hasIndex('cotacoes', 'cotacoes_tenant_req_fornecedor_uq')) {
            return;
        }

        // MySQL/InnoDB: o unique antigo é o índice que dá suporte à FK de requisicao_id
        // (ele está na ponta esquerda). Dropá-lo direto dá erro 1553 — índice próprio
        // ANTES, como em estoque_minimos (2026_09_15_000003). No novo unique o
        // `tenant_id` passa a ser a ponta esquerda, então a FK não fica mais coberta.
        if (! Schema::hasIndex('cotacoes', 'cotacoes_requisicao_id_index')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->index('requisicao_id', 'cotacoes_requisicao_id_index');
            });
        }

        if (Schema::hasIndex('cotacoes', 'cotacoes_requisicao_id_fornecedor_id_deleted_at_unique')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropUnique('cotacoes_requisicao_id_fornecedor_id_deleted_at_unique');
            });
        }

        Schema::table('cotacoes', function (Blueprint $table) {
            $table->unique(['tenant_id', 'requisicao_id', 'fornecedor_id', 'deleted_at'], 'cotacoes_tenant_req_fornecedor_uq');
        });
    }

    /** Identidade de catálogo do saldo (unidade × depósito × item) — POR TENANT. */
    private function saldosEstoque(bool $sqlite): void
    {
        if (Schema::hasIndex('saldos_estoque', 'saldos_estoque_tenant_catalogo_uq')) {
            return;
        }

        if (Schema::hasIndex('saldos_estoque', 'saldos_estoque_catalogo_unique')) {
            $this->dropIndice('saldos_estoque', 'saldos_estoque_catalogo_unique');
        }

        DB::statement($sqlite
            ? 'CREATE UNIQUE INDEX saldos_estoque_tenant_catalogo_uq ON saldos_estoque '
                .'(tenant_id, unidade_id, deposito, item_catalogo_id) '
                .'WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL'
            // `catalogo_chave_unica` já é NULL fora do escopo (avulso ou tombstone).
            : 'CREATE UNIQUE INDEX saldos_estoque_tenant_catalogo_uq ON saldos_estoque (tenant_id, catalogo_chave_unica)');
    }

    /** DROP INDEX com a sintaxe de cada driver (MySQL exige o `ON tabela`). */
    private function dropIndice(string $tabela, string $indice): void
    {
        DB::getDriverName() === 'sqlite'
            ? DB::statement("DROP INDEX IF EXISTS {$indice}")
            : DB::statement("DROP INDEX {$indice} ON {$tabela}");
    }
};
