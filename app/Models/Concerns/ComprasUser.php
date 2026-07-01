<?php

namespace App\Models\Concerns;

use App\Enums\Perfil;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Capacidades do módulo de Compras sobre a base de identidade da fundação.
 *
 * Substitui as flags do app standalone (is_compradora/is_financeiro) por papéis
 * RBAC ('compras'/'financeiro'). Mantém o domínio existente do compras: o vínculo
 * usuário↔unidade continua no pivot `unidade_user` (perfil operacional + nível de
 * alçada) sobre `App\Models\Unidade`.
 */
trait ComprasUser
{
    /** Unidades às quais o usuário está vinculado, com perfil e nível de alçada. */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'unidade_user')
            ->withPivot(['perfil', 'nivel_alcada'])
            ->withTimestamps();
    }

    /**
     * Possui o perfil informado em qualquer unidade, ou os papéis globais
     * (Admin via flag da fundação; Compradora/Financeiro via role RBAC).
     */
    public function temPerfil(Perfil $perfil): bool
    {
        return match ($perfil) {
            Perfil::Admin => (bool) $this->is_admin,
            Perfil::CompradoraSenior => $this->hasRole('compras'),
            Perfil::Financeiro => $this->hasRole('financeiro'),
            default => $this->belongsToMany(Unidade::class, 'unidade_user')
                ->withoutGlobalScopes()
                ->withPivot('perfil')
                ->wherePivot('perfil', $perfil->value)
                ->exists(),
        };
    }

    /** Visualiza todas as unidades sem restrição (admin ou compradora sênior). */
    public function podeVerTodasUnidades(): bool
    {
        return (bool) $this->is_admin || $this->hasRole('compras');
    }

    /** Pode visualizar o módulo financeiro (contas a pagar). */
    public function podeVerPagamentos(): bool
    {
        return $this->hasRole('financeiro') || (bool) $this->is_admin;
    }

    /** Pode registrar/agendar/cancelar/reconciliar pagamentos. */
    public function podeGerenciarPagamentos(): bool
    {
        return $this->hasRole('financeiro') || (bool) $this->is_admin;
    }

    /** Staff de compras (papéis globais) — usado para 2FA obrigatório. */
    public function isComprasStaff(): bool
    {
        return $this->hasAnyRole(['compras', 'financeiro']);
    }
}
