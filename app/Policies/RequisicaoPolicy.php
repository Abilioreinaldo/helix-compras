<?php

namespace App\Policies;

use App\Models\Requisicao;
use App\Models\User;

/**
 * Autorização de Requisições de compra.
 *
 * Centraliza as regras hoje espalhadas nos componentes Livewire (escoping por
 * UnidadeScope + papéis globais + status). NÃO muda a regra: delega aos helpers do
 * User e ao próprio status da requisição.
 */
class RequisicaoPolicy
{
    /** Acessar a listagem — o resultado é escopado no próprio componente. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver/detalhar uma requisição: papéis globais (admin/compras sênior) veem todas; os
     * demais veem apenas as da(s) sua(s) unidade(s). Espelha o escoping de DetalheRequisicao.
     */
    public function view(User $user, Requisicao $requisicao): bool
    {
        return $user->podeVerTodasUnidades()
            || $user->unidades()->withoutGlobalScopes()->where('unidades.id', $requisicao->unidade_id)->exists();
    }

    /** Criar requisição: qualquer usuário autenticado (Fase 2 — "qualquer autenticado"). */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Editar — VISIBILIDADE: mesma regra do {@see view()} (papéis globais OU vínculo de
     * unidade). Fecha o IDOR: usuário de outra unidade não edita requisição alheia.
     *
     * O status editável (Rascunho/Devolvida) NÃO entra aqui de propósito: é regra de
     * NEGÓCIO e fica como guarda separada (abort_unless em FormularioRequisicao), senão o
     * Gate::before da fundação — que libera admin em qualquer ability — deixaria o admin
     * editar requisição em status não-editável.
     */
    public function update(User $user, Requisicao $requisicao): bool
    {
        return $this->view($user, $requisicao);
    }
}
