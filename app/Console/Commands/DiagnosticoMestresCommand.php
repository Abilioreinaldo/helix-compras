<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * D10: raio-x da atribuição de tenant dos dados mestres — gate de runbook para
 * o onboarding de um novo tenant. O retrofit de tenancy (migration 2026-08-04)
 * backfillou os mestres sem caminho de unidade (fornecedores, catálogo,
 * alçadas…) para o 1º tenant, correto enquanto a instalação é mono-tenant.
 * Antes de ativar um 2º tenant, rode este comando: ele é read-only, mostra a
 * distribuição por tenant e aponta (a) mestres sem tenant e (b) referências
 * cross-tenant (transacional de um tenant apontando para mestre de outro).
 * Com --strict, sai com código 1 se houver qualquer suspeita.
 */
class DiagnosticoMestresCommand extends Command
{
    protected $signature = 'compras:diagnostico-mestres {--strict : falha (exit 1) se houver atribuições suspeitas}';

    protected $description = 'Read-only: valida a atribuição de tenant dos dados mestres (rodar antes de ativar um novo tenant)';

    /** Mestres backfillados "para o 1º tenant" na migration 2026_08_04. */
    private const MESTRES = [
        'fornecedores', 'catalogo_itens', 'precos_homologados',
        'faixas_alcada', 'etapas_alcada', 'rateios_centrais',
    ];

    /** Referência transacional → mestre que deve concordar em tenant. */
    private const REFERENCIAS = [
        ['cotacoes', 'fornecedor_id', 'fornecedores'],
        ['pedidos_compra', 'fornecedor_id', 'fornecedores'],
        ['pagamentos', 'fornecedor_id', 'fornecedores'],
        ['requisicao_itens', 'item_catalogo_id', 'catalogo_itens'],
        ['itens_pedido_compra', 'item_catalogo_id', 'catalogo_itens'],
        ['saldos_estoque', 'item_catalogo_id', 'catalogo_itens'],
        ['requisicoes', 'faixa_alcada_id', 'faixas_alcada'],
    ];

    public function handle(): int
    {
        $suspeitas = 0;

        $linhas = [];

        foreach (self::MESTRES as $tabela) {
            $porTenant = DB::table($tabela)
                ->selectRaw('tenant_id, COUNT(*) as total')
                ->groupBy('tenant_id')
                ->get();

            foreach ($porTenant as $grupo) {
                $semTenant = $grupo->tenant_id === null;
                $suspeitas += $semTenant ? (int) $grupo->total : 0;
                $linhas[] = [$tabela, $grupo->tenant_id ?? '(NULL) ⚠', $grupo->total];
            }

            if ($porTenant->isEmpty()) {
                $linhas[] = [$tabela, '—', 0];
            }
        }

        $this->info('Distribuição dos mestres por tenant:');
        $this->table(['tabela', 'tenant_id', 'linhas'], $linhas);

        $this->newLine();
        $this->info('Referências cross-tenant (transacional × mestre):');

        foreach (self::REFERENCIAS as [$tabela, $coluna, $mestre]) {
            $divergentes = DB::table("{$tabela} as t")
                ->join("{$mestre} as m", 'm.id', '=', "t.{$coluna}")
                ->whereNotNull("t.{$coluna}")
                ->whereNotNull('t.tenant_id')
                ->whereNotNull('m.tenant_id')
                ->whereColumn('t.tenant_id', '!=', 'm.tenant_id')
                ->count();

            $suspeitas += $divergentes;

            $this->line(sprintf(
                '  %s %s.%s → %s: %d divergência(s)',
                $divergentes > 0 ? '⚠' : '✓',
                $tabela,
                $coluna,
                $mestre,
                $divergentes,
            ));
        }

        $this->newLine();

        if ($suspeitas > 0) {
            $this->warn("{$suspeitas} atribuição(ões) suspeita(s). Corrija ANTES de ativar um novo tenant.");

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Mestres consistentes — nenhum órfão de tenant, nenhuma referência cross-tenant.');

        return self::SUCCESS;
    }
}
