<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FK `tenant_id` → `tenants.id` nas tabelas de negócio do Compras.
 *
 * O backfill de 2026_08_04 criou a coluna e o índice, mas não a chave estrangeira.
 * A FK não é enfeite (kit de conformidade `tables_have_tenant_id`, fundação v0.2.0):
 *
 *  - é por ela que o off-boarding (platform:tenant-export / platform:tenant-purge)
 *    DESCOBRE as tabelas do cliente — sem FK, a tabela some do expurgo (LGPD art. 18);
 *  - é ela que impede um `tenant_id` órfão apontando para empresa inexistente;
 *  - o `cascadeOnDelete` faz o expurgo físico acompanhar a exclusão do tenant.
 *
 * Órfãos: se o backfill deixou algum `tenant_id` sem tenant correspondente, a FK
 * não poderia ser criada. Saneamos ANTES, zerando o órfão (a linha fica visível
 * para inspeção em vez de travar o deploy).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tabelas = [
        'unidades',
        'obras',
        'unidade_user',
        'faixas_alcada',
        'etapas_alcada',
        'auditorias',
        'fornecedores',
        'centros_custo',
        'requisicoes',
        'requisicao_itens',
        'requisicao_logs',
        'cotacoes',
        'itens_cotacao',
        'aprovacoes',
        'pedidos_compra',
        'itens_pedido_compra',
        'sequencias_pedido_compra',
        'sequencias_requisicao',
        'recebimentos',
        'itens_recebimento',
        'saldos_estoque',
        'movimentacoes_estoque',
        'lotes_estoque',
        'saldo_fusao_log',
        'catalogo_itens',
        'precos_homologados',
        'estoque_minimos',
        'requisicoes_material',
        'sessoes_inventario',
        'itens_inventario',
        'transferencias_estoque',
        'rateios_centrais',
        'rateio_unidades',
        'pagamentos',
        'reconciliacoes_bancarias',
        'itens_reconciliacao',
        'pedidos_loja_recebidos',
    ];

    /**
     * Índices ÚNICOS PARCIAIS (SQLite) que o rebuild de tabela do ALTER perde.
     *
     * No SQLite, acrescentar uma FK reconstrói a tabela: o Laravel recria os índices
     * que conhece, mas o `WHERE ...` dos índices parciais se perde — o índice volta
     * como unique TOTAL e, por exemplo, um pagamento soft-deletado passaria a impedir
     * a recriação do pagamento do mesmo pedido. Dropamos antes e recriamos depois,
     * com o mesmo SQL das migrations que os criaram. No MySQL estes índices são
     * uniques sobre COLUNA GERADA (não parciais) e o ALTER não reconstrói a tabela —
     * por isso o tratamento é SQLite-only.
     *
     * @var array<string, string>
     */
    private array $parciaisSqlite = [
        'saldos_estoque_catalogo_unique' => 'CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque '
            .'(unidade_id, deposito, item_catalogo_id) WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL',
        'lotes_estoque_saldo_lote_unique' => 'CREATE UNIQUE INDEX lotes_estoque_saldo_lote_unique ON lotes_estoque '
            .'(saldo_estoque_id, numero_lote) WHERE fundido_para_id IS NULL',
        'pagamentos_pedido_ativo_unique' => 'CREATE UNIQUE INDEX pagamentos_pedido_ativo_unique ON pagamentos '
            .'(pedido_compra_id) WHERE deleted_at IS NULL',
    ];

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            foreach (array_keys($this->parciaisSqlite) as $indice) {
                DB::statement("DROP INDEX IF EXISTS {$indice}");
            }
        }

        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'tenant_id') || $this->temFk($tabela)) {
                continue;
            }

            DB::table($tabela)
                ->whereNotNull('tenant_id')
                ->whereNotIn('tenant_id', fn ($q) => $q->select('id')->from('tenants'))
                ->update(['tenant_id' => null]);

            Schema::table($tabela, function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if ($sqlite) {
            foreach ($this->parciaisSqlite as $indice => $sql) {
                DB::statement("DROP INDEX IF EXISTS {$indice}");
                DB::statement($sql);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tabelas) as $tabela) {
            if (! Schema::hasTable($tabela) || ! $this->temFk($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }
    }

    private function temFk(string $tabela): bool
    {
        foreach (Schema::getForeignKeys($tabela) as $fk) {
            if (in_array('tenant_id', $fk['columns'] ?? [], true) && ($fk['foreign_table'] ?? null) === 'tenants') {
                return true;
            }
        }

        return false;
    }
};
