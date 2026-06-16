<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DefinirEstoqueMinimoAction
{
    /**
     * Define (ou remove) o estoque mínimo de um item de catálogo em uma unidade.
     *
     * - quantidadeMinima <= 0: remove o registro existente (se houver) e retorna null.
     * - quantidadeMinima > 0: cria ou atualiza o registro; retorna o model.
     *
     * @throws ValidationException quando o usuário não tem autorização ou o item é inválido.
     */
    public function execute(
        Unidade $unidade,
        CatalogoItem $item,
        float $quantidadeMinima,
        User $definidoPor,
    ): ?EstoqueMinimo {
        $this->verificarAutorizacao($unidade, $definidoPor);
        $this->verificarItem($item);

        if ($quantidadeMinima <= 0) {
            // Remove via instância (não query builder) para disparar o evento Eloquent e
            // a remoção ser registrada pelo trait Auditavel.
            $registro = EstoqueMinimo::where('unidade_id', $unidade->id)
                ->where('item_catalogo_id', $item->id)
                ->first();

            $registro?->delete();

            return null;
        }

        return $this->persistir($unidade->id, $item->id, $quantidadeMinima);
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function verificarAutorizacao(Unidade $unidade, User $usuario): void
    {
        $almoxarifeDaUnidade = $usuario->unidades()
            ->withoutGlobalScopes()
            ->where('unidades.id', $unidade->id)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->exists();

        $autorizado = $almoxarifeDaUnidade || $usuario->temPerfil(Perfil::Admin);

        if (! $autorizado) {
            throw ValidationException::withMessages([
                'autorizado' => 'Operação não permitida: usuário sem autorização para definir estoque mínimo nesta unidade.',
            ]);
        }
    }

    private function verificarItem(CatalogoItem $item): void
    {
        if (! $item->ativo || $item->trashed()) {
            throw ValidationException::withMessages([
                'item_catalogo_id' => 'Não é possível definir estoque mínimo para um item inativo ou removido.',
            ]);
        }
    }

    private function persistir(int $unidadeId, int $itemId, float $quantidade): EstoqueMinimo
    {
        try {
            return DB::transaction(function () use ($unidadeId, $itemId, $quantidade) {
                // Tenta bloquear linha existente para evitar race condition
                $existente = EstoqueMinimo::where('unidade_id', $unidadeId)
                    ->where('item_catalogo_id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    $existente->update(['quantidade_minima' => $quantidade]);

                    return $existente->fresh();
                }

                return EstoqueMinimo::create([
                    'unidade_id' => $unidadeId,
                    'item_catalogo_id' => $itemId,
                    'quantidade_minima' => $quantidade,
                ]);
            });
        } catch (QueryException $e) {
            // Violação de UNIQUE (SQLite: 19, MySQL: 1062) — relê e atualiza
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($codigo === 19 || $codigo === 1062) {
                $registro = EstoqueMinimo::where('unidade_id', $unidadeId)
                    ->where('item_catalogo_id', $itemId)
                    ->firstOrFail();

                $registro->update(['quantidade_minima' => $quantidade]);

                return $registro->fresh();
            }

            throw $e;
        }
    }
}
