<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_id nas tabelas de negócio do Compras (cura de fundo dos vazamentos
 * cross-tenant da revisão). Até aqui só `unidades` tinha a coluna; as demais 32
 * tabelas isolavam apenas por unidade (UnidadeScope) — um admin, que vê todas as
 * unidades, enxergava dados de QUALQUER tenant na base compartilhada de identidade.
 *
 * `bancos` fica de fora: é registro COMPE global (código 341 = Itaú é o mesmo para
 * todo tenant); a numeração de pedidos (`numero`, `ano+sequencia`) e o `codigo` de
 * requisição vêm de contadores globais monotônicos — já são únicos por construção,
 * então só ganham isolamento de leitura, não unique por-tenant.
 *
 * Backfill: DIRETAS puxam de `unidades.tenant_id` pela coluna de unidade;
 * ENCADEADAS herdam do pai já preenchido; sem caminho de unidade → 1º tenant
 * (a instalação é mono-tenant por design). Varredura final garante zero NULL.
 */
return new class extends Migration
{
    /** DIRETAS: coluna de unidade → unidades.tenant_id. */
    private array $diretas = [
        'obras' => 'unidade_id',
        'centros_custo' => 'unidade_id',
        'requisicoes' => 'unidade_id',
        'pedidos_compra' => 'unidade_id',
        'saldos_estoque' => 'unidade_id',
        'requisicoes_material' => 'unidade_id',
        'sessoes_inventario' => 'unidade_id',
        'estoque_minimos' => 'unidade_id',
        'rateio_unidades' => 'unidade_id',
        'transferencias_estoque' => 'unidade_destino_id',
        'saldo_fusao_log' => 'unidade_id_origem',
    ];

    /** ENCADEADAS: FK → pai (já preenchido acima) .tenant_id. Ordem importa. */
    private array $encadeadas = [
        'requisicao_itens' => ['requisicoes', 'requisicao_id'],
        'requisicao_logs' => ['requisicoes', 'requisicao_id'],
        'cotacoes' => ['requisicoes', 'requisicao_id'],
        'aprovacoes' => ['requisicoes', 'requisicao_id'],
        'itens_pedido_compra' => ['pedidos_compra', 'pedido_compra_id'],
        'recebimentos' => ['pedidos_compra', 'pedido_compra_id'],
        'pagamentos' => ['pedidos_compra', 'pedido_compra_id'],
        'itens_inventario' => ['sessoes_inventario', 'sessao_inventario_id'],
        'lotes_estoque' => ['saldos_estoque', 'saldo_estoque_id'],
        // 2 saltos: o pai também é uma encadeada preenchida acima.
        'itens_cotacao' => ['cotacoes', 'cotacao_id'],
        'itens_recebimento' => ['recebimentos', 'recebimento_id'],
        'itens_reconciliacao' => ['reconciliacoes_bancarias', 'reconciliacao_bancaria_id'],
    ];

    /** SEM caminho de unidade → 1º tenant (config/mestres por tenant). */
    private array $globaisPorTenant = [
        'fornecedores',
        'catalogo_itens',
        'rateios_centrais',
        'reconciliacoes_bancarias',
        'precos_homologados',
        'faixas_alcada',
        'etapas_alcada',
        'auditorias',
    ];

    private function todas(): array
    {
        return array_merge(
            array_keys($this->diretas),
            array_keys($this->encadeadas),
            $this->globaisPorTenant,
            ['movimentacoes_estoque'],
        );
    }

    public function up(): void
    {
        // 1) Coluna + índice em todas as tabelas tenant-owned.
        foreach ($this->todas() as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // 2) Backfill DIRETAS (raiz: unidades).
        foreach ($this->diretas as $tabela => $coluna) {
            DB::statement(
                "UPDATE {$tabela} SET tenant_id = ".
                "(SELECT u.tenant_id FROM unidades u WHERE u.id = {$tabela}.{$coluna}) ".
                'WHERE tenant_id IS NULL'
            );
        }

        // 3) Mestres sem unidade → 1º tenant ANTES das encadeadas que herdam deles.
        $primeiro = DB::table('tenants')->orderBy('created_at')->value('id');
        if ($primeiro !== null) {
            foreach ($this->globaisPorTenant as $tabela) {
                DB::table($tabela)->whereNull('tenant_id')->update(['tenant_id' => $primeiro]);
            }
        }

        // 4) Backfill ENCADEADAS (herdam do pai já preenchido).
        foreach ($this->encadeadas as $tabela => [$pai, $fk]) {
            DB::statement(
                "UPDATE {$tabela} SET tenant_id = ".
                "(SELECT p.tenant_id FROM {$pai} p WHERE p.id = {$tabela}.{$fk}) ".
                'WHERE tenant_id IS NULL'
            );
        }

        // 5) movimentacoes_estoque: saldo_estoque_id é nullable — COALESCE entre os
        //    pais alternativos (requisição de material, rateio, transferência).
        DB::statement(<<<'SQL'
            UPDATE movimentacoes_estoque SET tenant_id = COALESCE(
                (SELECT s.tenant_id  FROM saldos_estoque s          WHERE s.id  = movimentacoes_estoque.saldo_estoque_id),
                (SELECT rm.tenant_id FROM requisicoes_material rm   WHERE rm.id = movimentacoes_estoque.requisicao_material_id),
                (SELECT ru.tenant_id FROM rateio_unidades ru        WHERE ru.id = movimentacoes_estoque.rateio_unidade_id),
                (SELECT te.tenant_id FROM transferencias_estoque te WHERE te.id = movimentacoes_estoque.transferencia_estoque_id)
            ) WHERE tenant_id IS NULL
        SQL);

        // 6) Varredura final: qualquer órfão remanescente → 1º tenant.
        if ($primeiro !== null) {
            foreach ($this->todas() as $tabela) {
                DB::table($tabela)->whereNull('tenant_id')->update(['tenant_id' => $primeiro]);
            }
        }

        // 7) Chaves naturais que colidiriam entre tenants passam a ser por-tenant.
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropUnique('fornecedores_cnpj_deleted_at_unique');
            $table->unique(['tenant_id', 'cnpj', 'deleted_at'], 'fornecedores_tenant_cnpj_uq');
        });
        Schema::table('catalogo_itens', function (Blueprint $table) {
            $table->dropUnique('catalogo_itens_codigo_deleted_at_unique');
            $table->unique(['tenant_id', 'codigo', 'deleted_at'], 'catalogo_itens_tenant_codigo_uq');
        });
        Schema::table('rateios_centrais', function (Blueprint $table) {
            $table->dropUnique('rateios_centrais_mes_ano_unique');
            $table->unique(['tenant_id', 'mes', 'ano'], 'rateios_centrais_tenant_mes_ano_uq');
        });
        Schema::table('reconciliacoes_bancarias', function (Blueprint $table) {
            $table->dropUnique('reconciliacoes_bancarias_arquivo_hash_unique');
            $table->unique(['tenant_id', 'arquivo_hash'], 'reconciliacoes_bancarias_tenant_hash_uq');
        });
    }

    public function down(): void
    {
        // Reverte uniques por-tenant → globais.
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropUnique('fornecedores_tenant_cnpj_uq');
            $table->unique(['cnpj', 'deleted_at']);
        });
        Schema::table('catalogo_itens', function (Blueprint $table) {
            $table->dropUnique('catalogo_itens_tenant_codigo_uq');
            $table->unique(['codigo', 'deleted_at']);
        });
        Schema::table('rateios_centrais', function (Blueprint $table) {
            $table->dropUnique('rateios_centrais_tenant_mes_ano_uq');
            $table->unique(['mes', 'ano']);
        });
        Schema::table('reconciliacoes_bancarias', function (Blueprint $table) {
            $table->dropUnique('reconciliacoes_bancarias_tenant_hash_uq');
            $table->unique('arquivo_hash');
        });

        foreach ($this->todas() as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
