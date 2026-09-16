<?php

namespace App\Policies;

use App\Enums\Perfil;
use App\Models\RequisicaoMaterial;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;

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

    /**
     * Operar sobre ESTA RIM (atender/recusar pelo almoxarife, salvar pelo solicitante)
     * numa tela cuja permissão de módulo já foi checada: a policy responde a posse do
     * tenant. Desde a fundação v0.2.0 o Gate::before não curto-circuita com um model.
     */
    public function operar(User $user, RequisicaoMaterial $rim): bool
    {
        $ativo = TenantContext::id() ?? $user->getActiveTenantId();

        return $ativo !== null
            && $rim->tenant_id !== null
            && (string) $rim->tenant_id === (string) $ativo;
    }
}
