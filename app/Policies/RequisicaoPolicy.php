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
     * Editar: permitido enquanto o status permitir edição (Rascunho/Devolvida). Espelha
     * EXATAMENTE o abort_unless atual de FormularioRequisicao (mount) — hoje NÃO checa
     * unidade/dono (ver follow-up de IDOR sinalizado à parte). Ao endurecer, basta compor
     * com view().
     */
    public function update(User $user, Requisicao $requisicao): bool
    {
        return $requisicao->status->permiteEdicao();
    }
}
