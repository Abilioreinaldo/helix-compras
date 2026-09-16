<?php

namespace App\Console\Commands;

use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Relatório de SANEAMENTO multitenant — SOMENTE LEITURA (decisão 15, passo 1 do plano
 * do DBA; ver docs/SANEAMENTO-TENANT.md).
 *
 * Para cada FK de domínio `filha.coluna → mãe` (as duas com tenant_id), descoberta no
 * SCHEMA das tabelas criadas pelas migrations do app:
 *  - órfãos:  filha aponta para uma mãe que não existe;
 *  - cruzados: a mãe existe, mas em OUTRO tenant.
 * Mais: `tenant_id` nulo por tabela e, só informativo, as FKs para `users` cujo usuário
 * não tem vínculo ATIVO (tenant_user.status = active) no tenant da linha.
 *
 * Roda sob TenantContext::runAsPlatform e só emite SELECT (provado em teste com um
 * listener que recusa INSERT/UPDATE/DELETE/DDL). Exit code 1 quando há órfão ou cruzado.
 * Rodar em CÓPIA da produção, com usuário de banco somente leitura.
 */
class IntegridadeTenantCommand extends Command
{
    protected $signature = 'helix:integridade-tenant {--json : Saída em JSON}';

    protected $description = 'Relatório SÓ LEITURA de integridade multitenant: FKs órfãs/cruzadas, tenant_id nulo e autoria sem vínculo ativo.';

    public function handle(): int
    {
        $relatorio = TenantContext::runAsPlatform(fn () => $this->levantar());

        if ($this->option('json')) {
            $this->line(json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->imprimir($relatorio);
        }

        return $relatorio['resumo']['integro'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{driver: string, fks: list<array<string, mixed>>, tenant_id_nulos: list<array<string, mixed>>, fks_usuarios: list<array<string, mixed>>, resumo: array<string, mixed>}
     */
    private function levantar(): array
    {
        $tabelas = array_values(array_filter(
            $this->tabelasDasMigrations(),
            fn (string $t) => Schema::hasTable($t) && Schema::hasColumn($t, 'tenant_id'),
        ));

        $fks = [];
        $fksUsuarios = [];

        foreach ($tabelas as $filha) {
            foreach (Schema::getForeignKeys($filha) as $fk) {
                $pares = array_combine($fk['columns'], $fk['foreign_columns']);
                unset($pares['tenant_id']); // FK composta (col, tenant_id) → (id, tenant_id) e a própria FK de tenant

                if (count($pares) !== 1) {
                    continue;
                }

                $coluna = (string) array_key_first($pares);
                $mae = $fk['foreign_table'];
                $chave = "{$filha}.{$coluna}->{$mae}";

                if ($mae === 'users') {
                    $fksUsuarios[$chave] ??= ['filha' => $filha, 'coluna' => $coluna, 'mae' => 'users'];

                    continue;
                }

                // Mãe sem tenant_id (catálogo global) não tem "outro tenant" a cruzar.
                if (! Schema::hasTable($mae) || ! Schema::hasColumn($mae, 'tenant_id')) {
                    continue;
                }

                $fks[$chave] ??= ['filha' => $filha, 'coluna' => $coluna, 'mae' => $mae, 'coluna_mae' => (string) $pares[$coluna]];
            }
        }

        $profundidade = $this->profundidades($tabelas, $fks);
        $ordenar = fn (array $a, array $b) => [$profundidade[$a['mae']] ?? 0, $profundidade[$a['filha']] ?? 0, $a['filha'], $a['coluna']]
            <=> [$profundidade[$b['mae']] ?? 0, $profundidade[$b['filha']] ?? 0, $b['filha'], $b['coluna']];

        $fks = array_values($fks);
        usort($fks, $ordenar);
        foreach ($fks as &$fk) {
            $fk['orfaos'] = $this->contarOrfaos($fk['filha'], $fk['coluna'], $fk['mae'], $fk['coluna_mae']);
            $fk['cruzados'] = $this->contarCruzados($fk['filha'], $fk['coluna'], $fk['mae'], $fk['coluna_mae']);
        }
        unset($fk);

        $fksUsuarios = array_values($fksUsuarios);
        usort($fksUsuarios, fn (array $a, array $b) => [$profundidade[$a['filha']] ?? 0, $a['filha'], $a['coluna']]
            <=> [$profundidade[$b['filha']] ?? 0, $b['filha'], $b['coluna']]);
        foreach ($fksUsuarios as &$fk) {
            $fk['sem_vinculo_ativo'] = $this->contarSemVinculoAtivo($fk['filha'], $fk['coluna']);
        }
        unset($fk);

        usort($tabelas, fn (string $a, string $b) => [$profundidade[$a] ?? 0, $a] <=> [$profundidade[$b] ?? 0, $b]);
        $nulos = array_map(fn (string $t) => ['tabela' => $t, 'nulos' => $this->contarTenantNulo($t)], $tabelas);

        $orfaos = array_sum(array_column($fks, 'orfaos'));
        $cruzados = array_sum(array_column($fks, 'cruzados'));

        return [
            'driver' => DB::getDriverName(),
            'fks' => $fks,
            'tenant_id_nulos' => $nulos,
            'fks_usuarios' => $fksUsuarios,
            'resumo' => [
                'fks_verificadas' => count($fks),
                'orfaos' => $orfaos,
                'cruzados' => $cruzados,
                'tenant_id_nulos' => array_sum(array_column($nulos, 'nulos')),
                'sem_vinculo_ativo' => array_sum(array_column($fksUsuarios, 'sem_vinculo_ativo')),
                'integro' => $orfaos === 0 && $cruzados === 0,
            ],
        ];
    }

    /** Filhas cuja mãe não existe (restore parcial, FK desligada, import). */
    private function contarOrfaos(string $filha, string $coluna, string $mae, string $colunaMae): int
    {
        return DB::table("{$filha} as f")
            ->whereNotNull("f.{$coluna}")
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from("{$mae} as m")->whereColumn("m.{$colunaMae}", "f.{$coluna}"))
            ->count();
    }

    /** Filhas cuja mãe existe em OUTRO tenant (os dois tenant_id preenchidos e diferentes). */
    private function contarCruzados(string $filha, string $coluna, string $mae, string $colunaMae): int
    {
        return DB::table("{$filha} as f")
            ->whereNotNull("f.{$coluna}")
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from("{$mae} as m")
                ->whereColumn("m.{$colunaMae}", "f.{$coluna}")
                ->whereColumn('m.tenant_id', '<>', 'f.tenant_id'))
            ->count();
    }

    private function contarTenantNulo(string $tabela): int
    {
        return DB::table($tabela)->whereNull('tenant_id')->count();
    }

    /** Informativo: autoria cujo usuário não tem vínculo ATIVO no tenant da linha. */
    private function contarSemVinculoAtivo(string $filha, string $coluna): int
    {
        return DB::table("{$filha} as f")
            ->whereNotNull("f.{$coluna}")
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tenant_user as tu')
                ->whereColumn('tu.user_id', "f.{$coluna}")
                ->whereColumn('tu.tenant_id', 'f.tenant_id')
                ->where('tu.status', 'active'))
            ->count();
    }

    /**
     * Tabelas criadas pelas migrations DO APP (database/migrations, recursivo). As da
     * fundação ficam fora — entram só como mães.
     *
     * @return list<string>
     */
    private function tabelasDasMigrations(): array
    {
        $tabelas = [];

        foreach (File::allFiles(database_path('migrations')) as $arquivo) {
            if (preg_match_all('/Schema::create\s*\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $arquivo->getContents(), $m)) {
                array_push($tabelas, ...$m[1]);
            }
        }

        $tabelas = array_values(array_unique($tabelas));
        sort($tabelas);

        return $tabelas;
    }

    /**
     * Profundidade no grafo de FKs de domínio (0 = raiz): ordena a saída mãe → filha.
     *
     * @param  list<string>  $tabelas
     * @param  array<string, array{filha: string, mae: string}>  $fks
     * @return array<string, int>
     */
    private function profundidades(array $tabelas, array $fks): array
    {
        $maes = [];
        foreach ($fks as $fk) {
            if ($fk['mae'] !== $fk['filha']) {
                $maes[$fk['filha']][$fk['mae']] = true;
            }
        }

        $memo = [];
        $calcular = function (string $tabela, array $caminho) use (&$calcular, &$memo, $maes): int {
            if (isset($memo[$tabela])) {
                return $memo[$tabela];
            }
            if (isset($caminho[$tabela])) {
                return 0; // ciclo: corta
            }
            $caminho[$tabela] = true;
            $nivel = 0;
            foreach (array_keys($maes[$tabela] ?? []) as $mae) {
                $nivel = max($nivel, 1 + $calcular($mae, $caminho));
            }

            return $memo[$tabela] = $nivel;
        };

        foreach ($tabelas as $tabela) {
            $calcular($tabela, []);
        }
        foreach ($fks as $fk) {
            $calcular($fk['mae'], []);
        }

        return $memo;
    }

    /** @param  array<string, mixed>  $r */
    private function imprimir(array $r): void
    {
        $this->info('Integridade multitenant (somente leitura) — driver '.$r['driver']);

        $this->newLine();
        $this->line('<comment>FKs de domínio (mãe → filha)</comment>');
        $this->table(['Mãe', 'Filha.coluna', 'Órfãos', 'Cruzados'], array_map(
            fn ($fk) => [$fk['mae'], $fk['filha'].'.'.$fk['coluna'], $fk['orfaos'], $fk['cruzados']],
            $r['fks'],
        ));

        $this->line('<comment>tenant_id nulo por tabela</comment>');
        $this->table(['Tabela', 'Nulos'], array_map(fn ($n) => [$n['tabela'], $n['nulos']], $r['tenant_id_nulos']));

        $this->line('<comment>FKs para users — sem vínculo ativo no tenant (informativo, não reprova)</comment>');
        $this->table(['Filha.coluna', 'Sem vínculo ativo'], array_map(
            fn ($fk) => [$fk['filha'].'.'.$fk['coluna'], $fk['sem_vinculo_ativo']],
            $r['fks_usuarios'],
        ));

        $s = $r['resumo'];
        $linha = "FKs verificadas: {$s['fks_verificadas']} | órfãos: {$s['orfaos']} | cruzados: {$s['cruzados']} | "
            ."tenant_id nulos: {$s['tenant_id_nulos']} | autoria sem vínculo ativo: {$s['sem_vinculo_ativo']}";

        $s['integro'] ? $this->info($linha) : $this->error($linha.' — HÁ ÓRFÃOS/CRUZADOS: não avance para as FKs compostas.');
    }
}
