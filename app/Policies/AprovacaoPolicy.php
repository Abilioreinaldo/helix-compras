<?php

namespace App\Policies;

use App\Enums\Perfil;
use App\Models\Requisicao;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Autorização de Aprovações.
 *
 * Como `Requisicao` já tem `RequisicaoPolicy`, estas habilidades são registradas como
 * Gates nomeados (`aprovacao.*`) no AppServiceProvider. NÃO muda a regra: espelha os
 * checks hoje espalhados em FilaAprovacoes e PainelAprovacao. O nível-por-etapa da
 * DECISÃO em si (aprovar/reprovar) segue validado nas Actions (ValidationException),
 * com mensagem amigável e modal aberto — não vira 403.
 *
 * Tenant: `acessar`/`decidir` comparam o tenant da requisição com o tenant ativo e
 * só contam vínculos (unidade_user) do mesmo tenant — aprovador de A nunca decide em B.
 *
 * Admin do tenant: até a fundação v0.1.x ele passava pelo `Gate::before` ("admin passa
 * por tudo"), inclusive com um registro como argumento. A v0.2.0 deixou de curto-circuitar
 * quando há model — a decisão é da policy. O bypass do admin fica DECLARADO aqui (depois
 * da comparação de tenant, portanto sempre confinado à empresa dele), preservando o
 * comportamento anterior sem reabrir o furo cross-tenant.
 */
class AprovacaoPolicy
{
    /** Acessar a fila de aprovações: ter o perfil Aprovador em alguma unidade. */
    public function acessarFila(User $user): bool
    {
        return $user->temPerfil(Perfil::Aprovador);
    }

    /**
     * Acessar o painel de uma requisição: ser Aprovador NA unidade dela (anti-IDOR).
     * Espelha PainelAprovacao::carregarRequisicao (403).
     */
    public function acessar(User $user, Requisicao $requisicao): bool
    {
        if (! $this->mesmoTenant($user, $requisicao)) {
            return false;
        }

        if ($user->isAdminForActiveTenant()) {
            return true;
        }

        return $this->vinculoAprovador($user, $requisicao)->exists();
    }

    /**
     * Decidir a ETAPA ATUAL: ser Aprovador na unidade E com o nível exigido pela etapa
     * pendente. Espelha PainelAprovacao::podeAprovar (flag de UI). A decisão efetiva é
     * revalidada nas Actions.
     */
    public function decidir(User $user, Requisicao $requisicao): bool
    {
        if (! $this->mesmoTenant($user, $requisicao)) {
            return false;
        }

        if ($user->isAdminForActiveTenant()) {
            return true;
        }

        $etapa = $requisicao->etapaAprovacaoAtual();
        if (! $etapa) {
            return false;
        }

        return $this->vinculoAprovador($user, $requisicao)
            ->where('nivel_alcada', $etapa->nivel_exigido->value)
            ->exists();
    }

    private function vinculoAprovador(User $user, Requisicao $requisicao): Builder
    {
        return DB::table('unidade_user')
            ->where('tenant_id', $requisicao->tenant_id)
            ->where('user_id', $user->getKey())
            ->where('unidade_id', $requisicao->unidade_id)
            ->where('perfil', Perfil::Aprovador->value);
    }

    /**
     * COMPRAS-6 (4ª auditoria, sonda P4-U8): além de conferir o tenant, a policy exige
     * VÍNCULO ATIVO (`tenant_user.status = active`) nele. Sem isso, quem foi SUSPENSO na
     * empresa mas ainda tem linha viva em `unidade_user` recebia `true` da policy chamada
     * direto — pela web o middleware `tenant.ativo` já devolvia 403, então não havia
     * caminho vivo, mas qualquer job/comando/API futuro que chamasse a policy sem passar
     * pelo middleware herdaria o buraco. A policy falha FECHADA por conta própria.
     */
    private function mesmoTenant(User $user, Requisicao $requisicao): bool
    {
        $tenantAtivo = TenantContext::id() ?? $user->getActiveTenantId();

        return $tenantAtivo !== null
            && $requisicao->tenant_id !== null
            && (string) $requisicao->tenant_id === (string) $tenantAtivo
            && $user->belongsToTenant((string) $requisicao->tenant_id);
    }
}
