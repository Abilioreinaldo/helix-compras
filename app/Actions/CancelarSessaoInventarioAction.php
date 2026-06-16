<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusInventario;
use App\Models\SessaoInventario;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelarSessaoInventarioAction
{
    /**
     * Cancela uma sessão de inventário em andamento, sem gerar movimentações.
     *
     * @throws ValidationException
     */
    public function execute(SessaoInventario $sessao, User $canceladoPor): SessaoInventario
    {
        if ($sessao->status !== StatusInventario::EmAndamento) {
            throw ValidationException::withMessages([
                'status' => 'Somente sessões em andamento podem ser canceladas.',
            ]);
        }

        // Valida perfil: Admin (global) ou Almoxarife da unidade
        $autorizado = $canceladoPor->temPerfil(Perfil::Admin)
            || $canceladoPor->unidades()
                ->withoutGlobalScopes()
                ->where('unidades.id', $sessao->unidade_id)
                ->wherePivot('perfil', Perfil::Almoxarife->value)
                ->exists();

        if (! $autorizado) {
            abort(403, 'Perfil insuficiente para cancelar inventário.');
        }

        $sessao->update(['status' => StatusInventario::Cancelado]);

        return $sessao->refresh();
    }
}
