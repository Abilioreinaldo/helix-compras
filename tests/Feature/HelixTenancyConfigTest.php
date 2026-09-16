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
it('mantém em runtime todas as subchaves de foundation.tenancy que o pacote define', function () {
    $package = require base_path('vendor/helix/foundation/config/foundation.php');

    expect($package)->toHaveKey('tenancy');

    $missing = [];
    $walk = function (array $expected, mixed $actual, string $path) use (&$walk, &$missing): void {
        foreach ($expected as $key => $value) {
            $keyPath = "{$path}.{$key}";

            if (! is_array($actual) || ! array_key_exists($key, $actual)) {
                $missing[] = $keyPath;

                continue;
            }

            // Só desce em mapas: listas (exclude_tables, export_redact) são valores do app.
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $walk($value, $actual[$key], $keyPath);
            }
        }
    };

    $walk($package['tenancy'], config('foundation.tenancy'), 'foundation.tenancy');

    expect($missing)->toBe([], 'Subchaves do pacote ausentes no config do app (merge raso): '.implode(', ', $missing));
});
