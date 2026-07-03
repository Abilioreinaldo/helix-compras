<?php

namespace App\Policies;

use App\Enums\Perfil;
use App\Models\User;

/**
 * Autorização de ACESSO ao módulo de Estoque/Almoxarife (saldos, recebimento, inventário,
 * atendimento de RIM, mapa de estoque).
 *
 * Gate nomeado `estoque.gerenciar` (sem model). NÃO muda a regra: espelha o
 * `temPerfil(Almoxarife)` dos componentes. O admin entra via Gate::before da fundação —
 * o que já era o caso onde o check era `Admin || Almoxarife`.
 *
 * IMPORTANTE: isto é só ACESSO. Regras de ESTADO/WORKFLOW (status do pedido == Emitido,
 * saldo, lote, quantidade, invariantes de fusão/FEFO) seguem validadas nos componentes e
 * nas Actions/serviços (abort_unless/ValidationException) — NÃO viram Gate, senão o admin
 * as furaria via Gate::before.
 */
class EstoquePolicy
{
    /** Acessar/operar o módulo de estoque: ter o perfil Almoxarife em alguma unidade. */
    public function gerenciar(User $user): bool
    {
        return $user->temPerfil(Perfil::Almoxarife);
    }
}
