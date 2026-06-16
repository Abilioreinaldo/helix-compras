<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusRequisicaoMaterial;
use App\Models\RequisicaoMaterial;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecusarRequisicaoMaterialAction
{
    /**
     * Recusa uma Requisição Interna de Material.
     *
     * @throws ValidationException
     */
    public function execute(RequisicaoMaterial $rim, User $almoxarife, string $motivo): RequisicaoMaterial
    {
        if ($rim->status !== StatusRequisicaoMaterial::Aberta) {
            throw ValidationException::withMessages([
                'status' => 'Somente requisições com status "Aberta" podem ser recusadas.',
            ]);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo' => 'O motivo da recusa é obrigatório.',
            ]);
        }

        // Valida que o almoxarife pertence à unidade da RIM
        $pertenceAUnidade = $almoxarife->unidades()
            ->withoutGlobalScopes()
            ->where('unidades.id', $rim->unidade_id)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->exists();

        if (! $pertenceAUnidade) {
            throw ValidationException::withMessages([
                'almoxarife' => 'Almoxarife não pertence à unidade desta requisição.',
            ]);
        }

        $rim->update([
            'status' => StatusRequisicaoMaterial::Recusada,
            'almoxarife_id' => $almoxarife->id,
            'motivo_recusa' => $motivo,
            'recusada_em' => now(),
        ]);

        return $rim->refresh();
    }
}
