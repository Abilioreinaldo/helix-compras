<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorização do módulo Financeiro (contas a pagar).
 *
 * Centraliza os checks antes espalhados em abort_unless(...podeVerPagamentos()/
 * ...podeGerenciarPagamentos()) pelos componentes Livewire. NÃO muda a regra:
 * delega aos helpers de papel do User (papel global `financeiro` ou admin).
 *
 * Pagamentos são globais por papel — a tabela `pagamentos` não tem
 * unidade_id/tenant_id (ver PLANO.md, "Nota de revisão de plataforma").
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
}
