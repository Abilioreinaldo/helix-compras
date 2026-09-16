<?php

/*
 * O mergeConfigFrom da fundação é RASO: um bloco `tenancy` declarado no
 * config/foundation.php do app SUBSTITUI o do pacote inteiro. Um bloco
 * incompleto derrubava subchaves em silêncio — People, Store e Fuel perderam
 * `offboarding` (exclude_tables, edges, export_redact) até a v0.3.3.
 *
 * Este teste compara, recursivamente, as chaves que o pacote define em
 * `tenancy` com as que existem em runtime: a próxima subchave nova da fundação
 * que o app não replicar quebra o CI em vez de passar calada.
 */

/** @return list<string> caminhos de chave que o pacote define e o runtime não tem */
function subchavesAusentes(array $expected, mixed $actual, string $path): array
{
    $missing = [];

    foreach ($expected as $key => $value) {
        $keyPath = "{$path}.{$key}";

        if (! is_array($actual) || ! array_key_exists($key, $actual)) {
            $missing[] = $keyPath;

            continue;
        }

        // Só desce em mapas: listas (exclude_tables, export_redact) são valores do app.
        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            array_push($missing, ...subchavesAusentes($value, $actual[$key], $keyPath));
        }
    }

    return $missing;
}

it('mantém em runtime todas as subchaves de foundation.tenancy que o pacote define', function () {
    $package = require base_path('vendor/helix/foundation/config/foundation.php');

    expect($package)->toHaveKey('tenancy');

    $missing = subchavesAusentes($package['tenancy'], config('foundation.tenancy'), 'foundation.tenancy');

    expect($missing)->toBe([], 'Subchaves do pacote ausentes no config do app (merge raso): '.implode(', ', $missing));
});

/*
 * v0.4.0 — o raso vale para QUALQUER bloco que o app redeclare, não só `tenancy`
 * (`login`, `two_factor`, `inbound`…): a subchave nova do pacote sumiria do mesmo
 * jeito. Hoje o app só redeclara `tenancy`; o teste cobre o próximo bloco.
 */
it('todo bloco de foundation.* que o app redeclara mantém as subchaves do pacote', function () {
    $package = require base_path('vendor/helix/foundation/config/foundation.php');
    $app = require config_path('foundation.php');

    $missing = [];
    foreach ($app as $key => $value) {
        $pkg = $package[$key] ?? null;

        if (is_array($pkg) && $pkg !== [] && ! array_is_list($pkg)) {
            array_push($missing, ...subchavesAusentes($pkg, config("foundation.{$key}"), "foundation.{$key}"));
        }
    }

    expect($missing)->toBe([], 'Subchaves do pacote ausentes no config do app (merge raso): '.implode(', ', $missing));
});

/*
 * v0.4.0 — os interruptores fail-closed são LITERAIS no arquivo do app (env vazia
 * virava false) e a rampa do superadmin fica desligada. Lê o ARQUIVO, não só o
 * runtime: um `env()` com default seguro passaria no runtime e cairia em produção.
 */
it('fail-closed literal no config do app e rampa do superadmin desligada', function () {
    $app = require config_path('foundation.php');

    expect($app['tenancy']['strict'])->toBeTrue()
        ->and($app['tenancy']['enforce_stamp'])->toBeTrue()
        ->and($app['tenancy']['superadmin_cross_tenant_argument'])->toBeFalse()
        ->and(config('foundation.tenancy.strict'))->toBeTrue()
        ->and(config('foundation.tenancy.enforce_stamp'))->toBeTrue()
        ->and(config('foundation.tenancy.superadmin_cross_tenant_argument'))->toBeFalse();

    $source = (string) file_get_contents(config_path('foundation.php'));
    expect($source)->not->toMatch("/'(strict|enforce_stamp|superadmin_cross_tenant_argument)'\s*=>\s*[^,]*env\s*\(/");
});

it('export_redact_columns é um mapa tabela => lista de colunas', function () {
    $columns = config('foundation.tenancy.offboarding.export_redact_columns');

    expect($columns)->toBeArray();

    foreach ($columns as $table => $list) {
        expect($table)->toBeString()
            ->and($list)->toBeArray()
            ->and(array_is_list($list))->toBeTrue();
    }
});
