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
     * Possui o perfil informado NO TENANT DA OPERAÇÃO: os globais (Admin, Compradora
     * sênior, Financeiro) via flag/permissão; os operacionais (Solicitante, Aprovador,
     * Almoxarife) via vínculo com alguma unidade.
     *
     * Fundação v0.7.0 (4ª auditoria, FUNDACAO-8): `hasPermission()`/`isAdmin…()` passaram
     * a avaliar o tenant do CONTEXTO (`TenantContext::id()`) e a NEGAR sem contexto. Este
     * método herda a regra: fora de request (console, fila, teste) ele responde `false`
     * até alguém declarar o tenant. Quem precisa perguntar por um tenant específico usa
     * {@see temPerfilEm()} — a forma explícita, como `hasPermissionIn`/`isAdminIn`.
     */
    public function temPerfil(Perfil $perfil): bool
    {
        $tenantId = $this->operationTenantId();

        return $tenantId !== null && $this->temPerfilEm($perfil, $tenantId);
    }

    /**
     * Possui o perfil NAQUELE tenant — a forma explícita (fundação v0.7.0), para o
     * console e para quem avalia OUTRA pessoa fora de um request.
     *
     * COMPRAS-7 (4ª auditoria): os comandos de rateio e de saneamento identificavam o
     * Admin executor ANTES de existir tenant no contexto e perguntavam `temPerfil()`, que
     * até a v0.6.x respondia pelo tenant HOME da identidade (`users.tenant_id`). Quem
     * fosse admin na empresa onde tem home operava com esse carimbo dentro de outra —
     * e, na v0.7.0, a mesma pergunta simplesmente NEGA. A pergunta certa é sempre
     * "neste tenant, esta pessoa é isto?".
     */
    public function temPerfilEm(Perfil $perfil, string $tenantId): bool
    {
        return match ($perfil) {
            Perfil::Admin => $this->isAdminIn($tenantId),
            Perfil::CompradoraSenior => $this->hasPermissionIn($tenantId, 'compras.manage'),
            Perfil::Financeiro => $this->hasPermissionIn($tenantId, 'pagamentos.manage'),
            // Perfil OPERACIONAL: mora no pivot unidade_user, escopado por tenant. O
            // runFor faz o `unidades()` filtrar pelo tenant perguntado (e não pelo do
            // contexto ambiente, que no console não existe).
            default => TenantContext::runFor((string) $tenantId, fn () => $this->unidades()
                ->withoutGlobalScope(UnidadeScope::class)
                ->wherePivot('perfil', $perfil->value)
                ->exists()),
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
