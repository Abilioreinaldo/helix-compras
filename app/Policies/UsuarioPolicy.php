<?php

namespace App\Policies;

use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;

/**
 * Posse de USUÁRIO — a identidade é COMPARTILHADA pela suíte e não tem "um tenant
 * dono": `users.tenant_id` é só o tenant HOME. Quem participa desta empresa é a
 * MEMBERSHIP ativa (pivot `tenant_user`), que é o mesmo critério da listagem
 * (ListaUsuarios::usuariosDoTenant) e da autorização da fundação.
 *
 * Por isso o usuário NÃO usa a {@see RecursoDoTenantPolicy} genérica (que compara
 * `tenant_id`): um convidado com home noutra empresa é legitimamente gerenciável
 * AQUI no que diz respeito ao VÍNCULO — e a restrição de editar a identidade dele
 * continua onde sempre esteve (abort_if ehConvidado, nos componentes).
 */
class UsuarioPolicy
{
    /** Operar sobre este usuário: ele tem de ser membro ATIVO do tenant ativo. */
    public function operar(User $ator, User $alvo): bool
    {
        $ativo = TenantContext::id() ?? $ator->getActiveTenantId();

        return $ativo !== null && $alvo->belongsToTenant((string) $ativo);
    }
}
