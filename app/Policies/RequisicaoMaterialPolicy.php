<?php

namespace App\Policies;

use App\Enums\Perfil;
use App\Models\User;

/**
 * Autorização da Requisição Interna de Material (RIM) do lado do solicitante.
 *
 * NÃO muda a regra: espelha o `temPerfil(Solicitante)` que os componentes checavam
 * inline — o perfil operacional vem do vínculo usuário×unidade (unidade_user), escopado
 * ao tenant ativo. O saldo escolhido é revalidado no próprio salvar (unidade do
 * solicitante + Rule::exists por tenant). Atendimento/recusa são do almoxarife
 * (Gate `estoque.gerenciar`).
 */
class RequisicaoMaterialPolicy
{
    /** Ver as próprias RIMs e abrir novas: ter o perfil Solicitante em alguma unidade. */
    public function create(User $user): bool
    {
        return $user->temPerfil(Perfil::Solicitante);
    }
}
