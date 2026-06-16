<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConfirmarVinculoSaldoAction
{
    /**
     * Vincula um saldo de estoque a um item de catálogo.
     *
     * Idempotente: se já vinculado ao mesmo item, é no-op. Se vinculado a outro item,
     * exige desvinculação prévia. Bloqueia colisão com saldo já existente na mesma
     * identidade (unidade, depósito, catálogo) — fusão de saldos é fora de escopo.
     * Nunca altera quantidade/custo_medio_ponderado/valor_total.
     *
     * @throws ValidationException
     */
    public function vincular(SaldoEstoque $saldo, CatalogoItem $item, User $admin): SaldoEstoque
    {
        abort_unless($admin->temPerfil(Perfil::Admin), 403);

        if ($saldo->item_catalogo_id === $item->id) {
            return $saldo;
        }

        if ($saldo->item_catalogo_id !== null) {
            throw ValidationException::withMessages([
                'item_catalogo_id' => 'Este saldo já está vinculado a outro item de catálogo. Desvincule antes de vincular a um novo item.',
            ]);
        }

        $colisao = SaldoEstoque::where('unidade_id', $saldo->unidade_id)
            ->where('deposito', $saldo->deposito)
            ->where('item_catalogo_id', $item->id)
            ->where('id', '!=', $saldo->id)
            ->exists();

        if ($colisao) {
            throw ValidationException::withMessages([
                'item_catalogo_id' => 'Já existe um saldo vinculado a este item de catálogo nesta unidade/depósito. A fusão de saldos não é suportada.',
            ]);
        }

        $saldo->update(['item_catalogo_id' => $item->id]);

        return $saldo;
    }

    /**
     * Remove o vínculo de catálogo de um saldo, retornando-o ao estado avulso.
     *
     * @throws ValidationException
     */
    public function desvincular(SaldoEstoque $saldo, User $admin): SaldoEstoque
    {
        abort_unless($admin->temPerfil(Perfil::Admin), 403);

        if ($saldo->item_catalogo_id === null) {
            return $saldo;
        }

        $saldo->update(['item_catalogo_id' => null]);

        return $saldo;
    }
}
