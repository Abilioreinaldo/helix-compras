<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

/*
| 3ª auditoria adversarial — achado 10 (kit), a partir da sonda de índices.
|
| A allowlist `allowGlobalUnique(...)` do kit de conformidade (HelixConformanceTest)
| é uma lista de EXCEÇÕES justificadas. Entrada que aponta para um índice que não
| existe mais (removido na 2ª auditoria) é pior que inútil: se alguém recriar um
| unique global com aquelas colunas, ele nasce "já perdoado". O kit da fundação
| não detecta entrada obsoleta — este teste detecta.
*/

uses(RefreshDatabase::class);

/**
 * Chaves `tabela.col1+col2` de todos os índices UNIQUE (não-PK) que NÃO contêm tenant_id.
 *
 * @return list<string>
 */
function uniquesGlobaisDoSchema(): array
{
    $chaves = [];

    foreach (Schema::getTableListing(schemaQualified: false) as $tabela) {
        foreach (Schema::getIndexes($tabela) as $indice) {
            $colunas = array_map('strtolower', $indice['columns'] ?? []);

            if (! ($indice['unique'] ?? false) || ($indice['primary'] ?? false) || in_array('tenant_id', $colunas, true)) {
                continue;
            }

            $chaves[] = $tabela.'.'.implode('+', $colunas);
        }
    }

    return $chaves;
}

/** @return list<string> as chaves declaradas em ->allowGlobalUnique('...') no teste do kit */
function allowlistDeUniquesGlobais(): array
{
    $fonte = file_get_contents(base_path('tests/Feature/HelixConformanceTest.php'));
    preg_match_all("/->allowGlobalUnique\\(\\s*'([^']+)'/", (string) $fonte, $m);

    return $m[1];
}

it('toda entrada allowGlobalUnique aponta para um unique global que EXISTE no schema', function () {
    // Índices parciais (SQLite) viram coluna gerada no MySQL, com outros nomes de
    // coluna: a comparação literal só é fiel no SQLite, onde a suíte principal roda.
    if (DB::getDriverName() !== 'sqlite') {
        Assert::markTestSkipped('Comparação de colunas de índice é SQLite-only (índices parciais viram coluna gerada no MySQL).');
    }

    $existentes = uniquesGlobaisDoSchema();
    $obsoletas = array_values(array_diff(allowlistDeUniquesGlobais(), $existentes));

    expect($obsoletas)->toBe([], 'allowlist com unique inexistente (remova a entrada): '.implode(', ', $obsoletas));
});

it('a allowlist de uniques globais não está vazia (o parser da fonte continua casando)', function () {
    expect(allowlistDeUniquesGlobais())->not->toBeEmpty();
});
