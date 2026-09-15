<?php

namespace App\Models\Concerns;

use App\Enums\Perfil;
use App\Models\Scopes\UnidadeScope;
use App\Models\Unidade;
use App\Models\UnidadeUser;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Capacidades do módulo de Compras sobre a base de identidade da fundação.
 *
 * Papéis GLOBAIS do módulo autorizam por PERMISSÃO (governada pelo admin da
 * empresa em Papéis & Permissões): compras.manage = compradora sênior,
 * pagamentos.manage = financeiro, compras.view = acesso ao módulo. Os papéis
 * canônicos do catálogo ('compras', 'financeiro') trazem esse padrão. O vínculo
 * usuário↔unidade (perfil operacional + nível de alçada) segue no pivot
 * `unidade_user` — é escopo de dados, não RBAC.
 */
trait ComprasUser
{
    /**
     * Unidades às quais o usuário está vinculado, com perfil e nível de alçada.
     *
     * O pivot (UnidadeUser) carimba `tenant_id` a partir da unidade; com tenant no
     * contexto, só vínculos DESTE tenant contam (defesa em profundidade além do
     * escopo de tenant da própria Unidade).
     */
    public function unidades(): BelongsToMany
    {
        $relacao = $this->belongsToMany(Unidade::class, 'unidade_user')
            ->using(UnidadeUser::class)
            ->withPivot(['tenant_id', 'perfil', 'nivel_alcada'])
            ->withTimestamps();

        if (($tenantId = TenantContext::id()) !== null) {
            $relacao->wherePivot('tenant_id', $tenantId);
        }

        return $relacao;
    }

    /**
     * Possui o perfil informado: os globais (Admin, Compradora sênior, Financeiro)
     * via flag/permissão; os operacionais (Solicitante, Aprovador, Almoxarife)
     * via vínculo com alguma unidade.
     */
    public function temPerfil(Perfil $perfil): bool
    {
        return match ($perfil) {
            Perfil::Admin => $this->isAdminForActiveTenant(),
            Perfil::CompradoraSenior => $this->hasPermission('compras.manage'),
            Perfil::Financeiro => $this->hasPermission('pagamentos.manage'),
            default => $this->unidades()
                ->withoutGlobalScope(UnidadeScope::class)
                ->wherePivot('perfil', $perfil->value)
                ->exists(),
        };
    }

    /** Visualiza todas as unidades sem restrição (admin ou compradora sênior). */
    public function podeVerTodasUnidades(): bool
    {
        return $this->hasPermission('compras.manage');
    }

    /** Pode visualizar o módulo financeiro (contas a pagar). */
    public function podeVerPagamentos(): bool
    {
        return $this->hasPermission('pagamentos.manage');
    }

    /** Pode registrar/agendar/cancelar/reconciliar pagamentos. */
    public function podeGerenciarPagamentos(): bool
    {
        return $this->hasPermission('pagamentos.manage');
    }

    /** Staff de compras (papéis globais) — usado para 2FA obrigatório. */
    public function isComprasStaff(): bool
    {
        return $this->hasPermission('compras.view');
    }
}
