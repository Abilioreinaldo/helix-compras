<?php

namespace App\Policies;

use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy de POSSE: "este registro é do tenant ativo?".
 *
 * Divisão de trabalho (fundação v0.2.0):
 *  - a PERMISSÃO do catálogo (`compras.manage`, `admin.gerenciar`, `estoque.gerenciar`,
 *    `users.manage`, …) responde "pode essa AÇÃO?" e continua checada no componente;
 *  - a POLICY responde "sobre ESSE registro?" — e só ela consegue, porque só ela recebe
 *    o registro. Até a v0.1.x o `Gate::before` curto-circuitava mesmo com um model no
 *    argumento: quem tinha a permissão passava sobre QUALQUER registro. A v0.2.0 parou
 *    de curto-circuitar e trouxe a decisão para cá.
 *
 * O escopo de leitura (BelongsToTenant do ComprasModel) já é fail-closed, mas ele é um
 * filtro de CONSULTA: qualquer `withoutGlobalScope`/`withoutTenantScope` no caminho o
 * dispensa. A autorização por registro é a segunda tranca, independente da consulta.
 *
 * Só posse: regras de ESTADO e workflow (status, saldo, alçada, invariantes de estoque)
 * seguem nos componentes e nas Actions — se virassem gate, o admin as furaria.
 */
class RecursoDoTenantPolicy
{
    /** Operar sobre este registro: ele tem de pertencer ao tenant ativo. */
    public function operar(User $user, Model $recurso): bool
    {
        $ativo = TenantContext::id() ?? $user->getActiveTenantId();
        $doRecurso = $recurso->getAttribute('tenant_id');

        return $ativo !== null
            && $doRecurso !== null
            && (string) $doRecurso === (string) $ativo;
    }
}
