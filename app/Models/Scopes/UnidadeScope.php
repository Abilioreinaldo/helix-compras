<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Limita a visibilidade de registros por UNIDADE conforme o perfil do usuário
 * autenticado. O isolamento por TENANT é responsabilidade do BelongsToTenant (base
 * ComprasModel), aplicado por baixo — aqui cuidamos só do recorte por unidade:
 *
 * - Admin e Compradora Senior: veem todas as unidades (do tenant ativo, via o
 *   escopo de tenant); este scope não acrescenta filtro de unidade.
 * - Demais perfis: veem apenas as unidades às quais estão vinculados.
 * - Sem autenticação ou sem vínculo: retorna zero linhas (falhar fechado).
 */
class UnidadeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        // Vê todas as unidades — o recorte por tenant já vem do BelongsToTenant.
        if ($user->podeVerTodasUnidades()) {
            return;
        }

        // Query direta na pivot para evitar recursão (a relação unidades() aplicaria este mesmo scope)
        $ids = DB::table('unidade_user')
            ->where('user_id', $user->getKey())
            ->pluck('unidade_id');

        if ($ids->isEmpty()) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $coluna = method_exists($model, 'colunaUnidade') ? $model::colunaUnidade() : 'id';

        $builder->whereIn($coluna, $ids);
    }
}
