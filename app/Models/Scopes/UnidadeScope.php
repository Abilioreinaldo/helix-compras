<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Limita a visibilidade de registros de unidades conforme o perfil do usuário autenticado.
 *
 * - Admin e Compradora Senior: veem todas as unidades.
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

        if ($user->podeVerTodasUnidades()) {
            // "todas" = todas do TENANT ativo, nunca da base inteira (multi-tenant).
            $this->filtraTenant($builder, $model, $user);

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

        // O vínculo user↔unidade já implica o tenant do usuário — não se aplica
        // filtro de tenant extra aqui (quebraria a relação `unidade` legítima de
        // um registro cuja unidade não está entre as vinculadas ao ator).
        $coluna = method_exists($model, 'colunaUnidade') ? $model::colunaUnidade() : 'id';

        $builder->whereIn($coluna, $ids);
    }

    /**
     * Amarra ao tenant ativo os modelos que expõem `colunaTenant()` (hoje só a
     * Unidade). Modelos sem a coluna seguem escopados apenas por unidade.
     */
    private function filtraTenant(Builder $builder, Model $model, $user): void
    {
        if (method_exists($model, 'colunaTenant')) {
            $builder->where($model->getTable().'.'.$model::colunaTenant(), $user->getActiveTenantId());
        }
    }
}
