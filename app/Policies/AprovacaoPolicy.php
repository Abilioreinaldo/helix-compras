<?php

namespace App\Policies;

use App\Enums\Perfil;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Autorização de Aprovações.
 *
 * Como `Requisicao` já tem `RequisicaoPolicy`, estas habilidades são registradas como
 * Gates nomeados (`aprovacao.*`) no AppServiceProvider. NÃO muda a regra: espelha os
 * checks hoje espalhados em FilaAprovacoes e PainelAprovacao. O nível-por-etapa da
 * DECISÃO em si (aprovar/reprovar) segue validado nas Actions (ValidationException),
 * com mensagem amigável e modal aberto — não vira 403.
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
        return DB::table('unidade_user')
            ->where('user_id', $user->getKey())
            ->where('unidade_id', $requisicao->unidade_id)
            ->where('perfil', Perfil::Aprovador->value)
            ->exists();
    }

    /**
     * Decidir a ETAPA ATUAL: ser Aprovador na unidade E com o nível exigido pela etapa
     * pendente. Espelha PainelAprovacao::podeAprovar (flag de UI). A decisão efetiva é
     * revalidada nas Actions.
     */
    public function decidir(User $user, Requisicao $requisicao): bool
    {
        $etapa = $requisicao->etapaAprovacaoAtual();
        if (! $etapa) {
            return false;
        }

        return DB::table('unidade_user')
            ->where('user_id', $user->getKey())
            ->where('unidade_id', $requisicao->unidade_id)
            ->where('perfil', Perfil::Aprovador->value)
            ->where('nivel_alcada', $etapa->nivel_exigido->value)
            ->exists();
    }
}
