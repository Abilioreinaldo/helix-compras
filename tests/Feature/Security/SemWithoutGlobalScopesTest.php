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

/**
 * SEGUNDA TRANCA (2ª auditoria adversarial).
 *
 * `withoutGlobalScope(UnidadeScope::class)` preserva o escopo de tenant, mas esse
 * escopo é um filtro de CONSULTA — e a consulta é justamente o que o caminho
 * alternativo dispensa: reidratação de model no Livewire (`newQueryForRestoration`
 * → `newQueryWithoutScopes()`), route-model binding explícito, um
 * `withoutTenantScope()` que entre no caminho amanhã. A autorização POR REGISTRO é
 * independente da consulta porque é a única que RECEBE o registro.
 *
 * Regra: toda porta de entrada (Livewire ou Controller) que carrega UM registro
 * fora do escopo de unidade tem de autorizar sobre esse registro — `can('...', $x)`
 * ou `authorize('...', $x)` com o registro no 2º argumento. Qual a habilidade
 * (`operar`, `view`, `aprovacao.acessar`) é decisão de cada tela; ter alguma, não é.
 *
 * Seis pontos estavam descobertos quando esta regra foi escrita: DetalhePedidoCompra,
 * FormularioPedidoCompra, GestaoCotacoes, MapaCotacao, RegistroRecebimento e
 * BaixarPdfPedidoCompraController.
 */
it('toda porta de entrada que carrega registro fora do escopo de unidade autoriza sobre ele', function () {
    $app = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'app';

    // Carga de UM registro com o escopo de unidade dispensado.
    $carregaRegistro = '/withoutGlobalScope\(UnidadeScope::class\)(?:.|\n){0,500}?(?:findOrFail|firstOrFail)\(/';
    // Registro vindo por route-model binding (o controller nem chega a consultar).
    $bindingDeModel = '/function\s+\w+\s*\([^)]*\b(?:Cotacao|PedidoCompra|Requisicao|SaldoEstoque|Fornecedor|CatalogoItem|Pagamento|Recebimento)\s+\$/';
    // Autorização COM o registro no 2º argumento (não basta a permissão do catálogo).
    $autorizaRegistro = '/(?:->can|authorize)\(\s*\'[^\']+\'\s*,\s*\$/';

    $violacoes = [];

    foreach (Finder::create()->files()->in([$app.'/Livewire', $app.'/Http/Controllers'])->name('*.php') as $arquivo) {
        $codigo = (string) file_get_contents($arquivo->getRealPath());

        $exposto = preg_match($carregaRegistro, $codigo) === 1
            || preg_match($bindingDeModel, $codigo) === 1;

        if ($exposto && preg_match($autorizaRegistro, $codigo) !== 1) {
            $violacoes[] = $arquivo->getRelativePathname();
        }
    }

    expect($violacoes)->toBe(
        [],
        "Porta de entrada carrega um registro (withoutGlobalScope de unidade, ou route-model binding) e NÃO autoriza sobre ele.\n"
        ."O escopo de tenant é filtro de consulta; a policy é a segunda tranca, e é a única que recebe o registro.\n"
        .'Acrescente can(\'operar\', $registro) (RecursoDoTenantPolicy) ou a habilidade própria da tela. '
        .'Arquivos: '.implode(', ', $violacoes),
    );
});
