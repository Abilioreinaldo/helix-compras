<?php

namespace App\Policies;

use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;

/**
 * Autorização de Requisições de compra.
 *
 * Centraliza as regras hoje espalhadas nos componentes Livewire (escoping por
 * UnidadeScope + papéis globais + status). NÃO muda a regra: delega aos helpers do
 * User e ao próprio status da requisição.
 *
 * Tenant: toda habilidade com model compara o tenant do recurso com o tenant ativo
 * ANTES de qualquer papel — admin/compradora do tenant A nunca enxerga nem edita
 * requisição de B. A fundação (v0.1.9) não dá mais bypass de admin em abilities sem
 * ponto (view/update), então o admin entra explicitamente por isAdminForActiveTenant().
 */
class RequisicaoPolicy
{
    /** Acessar a listagem — o resultado é escopado no próprio componente. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver/detalhar uma requisição: papéis globais (admin/compras sênior) veem todas DO
     * SEU TENANT; os demais veem apenas as da(s) sua(s) unidade(s).
     */
    public function view(User $user, Requisicao $requisicao): bool
    {
        if (! $this->mesmoTenant($user, $requisicao)) {
            return false;
        }

        return $user->isAdminForActiveTenant()
            || $user->podeVerTodasUnidades()
            || $user->unidades()->withoutGlobalScope(UnidadeScope::class)->where('unidades.id', $requisicao->unidade_id)->exists();
    }

    /** Criar requisição: qualquer usuário autenticado (Fase 2 — "qualquer autenticado"). */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Editar — VISIBILIDADE: mesma regra do {@see view()} (papéis globais OU vínculo de
     * unidade). Fecha o IDOR: usuário de outra unidade/tenant não edita requisição alheia.
     *
     * O status editável (Rascunho/Devolvida) NÃO entra aqui de propósito: é regra de
     * NEGÓCIO e fica como guarda separada (abort_unless em FormularioRequisicao), para o
     * admin também ficar sujeito ao status.
     */
    public function update(User $user, Requisicao $requisicao): bool
    {
        return $this->view($user, $requisicao);
    }

    /**
     * Operar sobre ESTA requisição numa tela cuja permissão de módulo já foi checada
     * (triagem/cotação da compradora): a policy responde só a posse do tenant. Desde a
     * fundação v0.2.0 o Gate::before não curto-circuita com um model no argumento.
     */
    public function operar(User $user, Requisicao $requisicao): bool
    {
        return $this->mesmoTenant($user, $requisicao);
    }

    /** O recurso pertence ao tenant ativo (contexto explícito ou do usuário autenticado). */
    private function mesmoTenant(User $user, Requisicao $requisicao): bool
    {
        $tenantAtivo = TenantContext::id() ?? $user->getActiveTenantId();

        return $tenantAtivo !== null
            && $requisicao->tenant_id !== null
            && (string) $requisicao->tenant_id === (string) $tenantAtivo;
    }
}
