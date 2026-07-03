<?php

namespace App\Policies;

use App\Enums\Perfil;
use App\Models\User;

/**
 * Autorização de ACESSO à administração (cadastros: unidades, usuários, fornecedores,
 * alçadas, centros de custo, catálogo, reconciliação; e a reversão de rateio central).
 *
 * Gate `admin.gerenciar`. NÃO muda a regra: espelha temPerfil(Admin) (is_admin). As rotas
 * `/admin` já têm o middleware `admin`; este gate é a mesma checagem nos componentes
 * (defesa em profundidade). Validações de NEGÓCIO dos cadastros seguem nos
 * $this->validate()/Actions (não viram Gate).
 */
class AdminPolicy
{
    /** Administrar cadastros e parâmetros: ter o perfil Admin (is_admin do tenant). */
    public function gerenciar(User $user): bool
    {
        return $user->temPerfil(Perfil::Admin);
    }
}
