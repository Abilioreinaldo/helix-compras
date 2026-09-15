<?php

use Symfony\Component\Finder\Finder;

/**
 * Teste de arquitetura (auditoria multitenant 2026-09-15).
 *
 * `withoutGlobalScopes()` remove TODOS os global scopes do model — inclusive o
 * escopo de tenant (`BelongsToTenant`, da fundação). Era assim que "ver todas as
 * unidades" virava "ver todos os tenants": compradora/admin de A lia, editava,
 * aprovava e transferia estoque de B. A forma correta de ampliar o recorte por
 * unidade é `withoutGlobalScope(\App\Models\Scopes\UnidadeScope::class)`, que
 * preserva o tenant; cruzar tenants de propósito (console/superadmin) é
 * `Model::withoutTenantScope()` — explícito e nomeado.
 */
it('nenhum arquivo em app/ usa withoutGlobalScopes() — remove o escopo de tenant', function () {
    $raiz = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'app';

    $violacoes = [];

    foreach (Finder::create()->files()->in($raiz)->name('*.php') as $arquivo) {
        $linhas = file($arquivo->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($linhas as $n => $linha) {
            if (str_contains($linha, 'withoutGlobalScopes(')) {
                $violacoes[] = $arquivo->getRelativePathname().':'.($n + 1);
            }
        }
    }

    expect($violacoes)->toBe(
        [],
        "withoutGlobalScopes() encontrado em app/ — ele remove TAMBÉM o escopo de tenant (BelongsToTenant) e vaza dados entre tenants.\n"
        .'Use withoutGlobalScope(\\App\\Models\\Scopes\\UnidadeScope::class) para ver todas as unidades DO TENANT, '
        ."ou Model::withoutTenantScope() (bypass explícito) quando cruzar tenants for intencional.\n"
        .'Ocorrências: '.implode(', ', $violacoes),
    );
});
