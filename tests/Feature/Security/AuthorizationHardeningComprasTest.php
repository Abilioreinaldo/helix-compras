<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Invariante arquitetural de autorização (D11 — replica no Compras a rede que o
 * relatório executivo credita ao Store).
 *
 * O Compras autoriza em camadas: `feature:compras` (entitlement) + a stack de
 * sessão em TODA página, `admin` nas telas administrativas, e `authorize`/policy
 * por-componente nas operacionais (triagem, aprovação, recebimento). A rede
 * estrutural que este teste fixa:
 *   1. toda página Livewire do app está sob `auth` + `feature:compras`;
 *   2. toda rota administrativa (`admin.*`) carrega o guard `admin`.
 * Uma tela nova exposta fora do grupo — ou uma tela admin fora do guard `admin` —
 * falha aqui, antes de virar rota desprotegida em produção. As telas de auth
 * (login, desafio 2FA) são da foundation (`Helix\Foundation\Livewire\*`) e ficam
 * de fora por construção.
 */
function comprasLivewirePages(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with((string) $r->getActionName(), 'App\\Livewire\\'));
}

it('há páginas Livewire do app para inspecionar', function () {
    expect(comprasLivewirePages())->not->toBeEmpty();
});

it('toda página Livewire do app está sob auth + feature:compras', function () {
    $violacoes = comprasLivewirePages()
        ->reject(fn (RoutingRoute $r) => in_array('auth', $mw = $r->gatherMiddleware(), true)
            && in_array('feature:compras', $mw, true))
        ->map(fn (RoutingRoute $r) => $r->uri())
        ->values()->all();

    expect($violacoes)->toBe([], 'páginas expostas sem auth+feature:compras: '.implode(', ', $violacoes));
});

it('toda rota administrativa (admin.*) carrega o guard admin', function () {
    $violacoes = comprasLivewirePages()
        ->filter(fn (RoutingRoute $r) => str_starts_with((string) $r->getName(), 'admin.'))
        ->reject(fn (RoutingRoute $r) => in_array('admin', $r->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $r) => $r->uri())
        ->values()->all();

    expect($violacoes)->toBe([], 'rotas admin.* sem o guard admin: '.implode(', ', $violacoes));
});
