<?php

namespace App\Policies;

use App\Models\Pagamento;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;

/**
 * Autorização do módulo Financeiro (contas a pagar).
 *
 * Centraliza os checks antes espalhados em abort_unless(...podeVerPagamentos()/
 * ...podeGerenciarPagamentos()) pelos componentes Livewire. NÃO muda a regra:
 * delega aos helpers de papel do User (papel global `financeiro` ou admin).
 *
 * Pagamentos são globais por papel (a leitura é escopada por tenant pelo
 * BelongsToTenant do model). O admin do tenant entra porque hasPermission()
 * da fundação já o contempla — não depende mais do Gate::before (v0.1.9 não
 * dá bypass em abilities sem ponto).
 */
class PagamentoPolicy
{
    /** Visualizar o módulo de pagamentos (listas, agendamentos). */
    public function viewAny(User $user): bool
    {
        return $user->podeVerPagamentos();
    }

    /** Registrar / agendar / cancelar / reconciliar pagamentos. */
    public function manage(User $user): bool
    {
        return $user->podeGerenciarPagamentos();
    }

    /**
     * Operar sobre ESTE pagamento: além do papel, o registro tem de ser do tenant ativo.
     * Desde a fundação v0.2.0 o Gate::before não curto-circuita quando há um model no
     * argumento — é aqui que o tenant do recurso é comparado.
     */
    public function operar(User $user, Pagamento $pagamento): bool
    {
        $ativo = TenantContext::id() ?? $user->getActiveTenantId();

        return $ativo !== null
            && $pagamento->tenant_id !== null
            && (string) $pagamento->tenant_id === (string) $ativo
            && $user->podeGerenciarPagamentos();
    }
}
