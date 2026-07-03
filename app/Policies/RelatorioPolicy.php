<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorização de ACESSO aos relatórios consolidados da rede.
 *
 * Gate `relatorio.ver`. NÃO muda a regra: espelha o `podeVerTodasUnidades()` (admin/
 * compras sênior) dos 9 relatórios uniformes.
 *
 * O RelatorioRateioMensalCentral tem regra PRÓPRIA (admin OU gestor/Aprovador da unidade,
 * com scoping dos dados por unidade e reversão admin-only) e segue com check bespoke no
 * componente — não cabe neste gate uniforme.
 */
class RelatorioPolicy
{
    /** Ver os relatórios consolidados: quem enxerga todas as unidades (admin ou compras sênior). */
    public function ver(User $user): bool
    {
        return $user->podeVerTodasUnidades();
    }
}
