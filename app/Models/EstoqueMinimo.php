<?php

namespace App\Models;

use App\Enums\Perfil;
use App\Models\Concerns\Auditavel;
use Database\Factories\EstoqueMinimoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EstoqueMinimo extends ComprasModel
{
    /** @use HasFactory<EstoqueMinimoFactory> */
    use Auditavel, HasFactory;

    protected $table = 'estoque_minimos';

    /** @var list<string> */
    protected $fillable = ['unidade_id', 'item_catalogo_id', 'quantidade_minima'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade_minima' => 'decimal:3',
        ];
    }

    // â”€â”€â”€ RelaÃ§Ãµes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function catalogoItem(): BelongsTo
    {
        return $this->belongsTo(CatalogoItem::class, 'item_catalogo_id');
    }

    // â”€â”€â”€ Leitura (mÃ©todos estÃ¡ticos) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Retorna itens abaixo do mÃ­nimo para o usuÃ¡rio informado.
     *
     * Visibilidade:
     * - podeVerTodasUnidades() â†’ rede inteira
     * - Almoxarife â†’ sÃ³ unidades do pivot
     * - outro â†’ coleÃ§Ã£o vazia
     *
     * Cada linha: {unidade_id, unidade_nome, item_catalogo_id, item_descricao,
     *              unidade_medida, quantidade_minima, saldo_atual, quantidade_sugerida}
     *
     * @return Collection<int, object>
     */
    public static function itensAReporPara(User $usuario): Collection
    {
        $unidadeIds = static::resolverUnidadeIds($usuario);

        if ($unidadeIds === null) {
            // podeVerTodasUnidades: busca sem filtro de unidade
            return static::queryItensARepor(null);
        }

        if ($unidadeIds->isEmpty()) {
            return collect();
        }

        return static::queryItensARepor($unidadeIds->toArray());
    }

    /**
     * Retorna os item_catalogo_id em alerta nas unidades informadas.
     *
     * @param  array<int>  $unidadeIds
     * @return array<int>
     */
    public static function itemCatalogoIdsEmAlerta(array $unidadeIds): array
    {
        if (empty($unidadeIds)) {
            return [];
        }

        return DB::table('estoque_minimos as em')
            ->join('unidades as u', function ($join) {
                $join->on('u.id', '=', 'em.unidade_id')
                    ->whereNull('u.deleted_at');
            })
            ->join('catalogo_itens as ci', function ($join) {
                $join->on('ci.id', '=', 'em.item_catalogo_id')
                    ->whereNull('ci.deleted_at')
                    ->where('ci.ativo', 1);
            })
            ->leftJoinSub(
                DB::table('saldos_estoque')
                    ->select('unidade_id', 'item_catalogo_id', DB::raw('SUM(quantidade) as saldo_total'))
                    ->whereNotNull('item_catalogo_id')
                    ->whereNull('fundido_para_id')
                    ->groupBy('unidade_id', 'item_catalogo_id'),
                's',
                function ($join) {
                    $join->on('s.unidade_id', '=', 'em.unidade_id')
                        ->on('s.item_catalogo_id', '=', 'em.item_catalogo_id');
                }
            )
            ->whereIn('em.unidade_id', $unidadeIds)
            ->whereRaw('COALESCE(s.saldo_total, 0) < em.quantidade_minima')
            ->pluck('em.item_catalogo_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * PosiÃ§Ã£o atual de estoque (todos os saldos vivos) visÃ­vel ao usuÃ¡rio.
     *
     * Exclui saldos fundidos (`fundido_para_id IS NOT NULL`) â€” tombstones de fusÃ£o
     * nÃ£o contam, evitando dupla contagem do saldo jÃ¡ migrado para o saldo-destino.
     * Para itens de catÃ¡logo, anexa `quantidade_minima` e a flag `em_alerta` via leftJoin;
     * saldos avulsos (sem `item_catalogo_id`) nÃ£o casam no leftJoin â†’ mÃ­nimo nulo.
     *
     * Visibilidade idÃªntica a itensAReporPara: rede inteira (podeVerTodasUnidades),
     * unidades do pivot (Almoxarife) ou coleÃ§Ã£o vazia.
     *
     * Cada linha: {saldo_id, unidade_id, unidade_nome, deposito, item_catalogo_id,
     *              descricao_item, unidade_medida, saldo_atual, custo_medio_ponderado,
     *              valor_total, quantidade_minima, em_alerta}
     *
     * @return Collection<int, object>
     */
    public static function posicaoEstoquePara(User $usuario): Collection
    {
        $unidadeIds = static::resolverUnidadeIds($usuario);

        if ($unidadeIds !== null && $unidadeIds->isEmpty()) {
            return collect();
        }

        $query = DB::table('saldos_estoque as s')
            ->join('unidades as u', function ($join) {
                $join->on('u.id', '=', 's.unidade_id')
                    ->whereNull('u.deleted_at');
            })
            ->leftJoin('estoque_minimos as em', function ($join) {
                $join->on('em.unidade_id', '=', 's.unidade_id')
                    ->on('em.item_catalogo_id', '=', 's.item_catalogo_id');
            })
            ->whereNull('s.fundido_para_id')
            ->select([
                's.id as saldo_id',
                's.unidade_id',
                'u.nome as unidade_nome',
                's.deposito',
                's.item_catalogo_id',
                's.descricao_item',
                's.unidade_medida',
                's.quantidade as saldo_atual',
                's.custo_medio_ponderado',
                's.valor_total',
                'em.quantidade_minima',
            ])
            ->orderBy('u.nome')
            ->orderBy('s.descricao_item');

        if ($unidadeIds !== null) {
            $query->whereIn('s.unidade_id', $unidadeIds->toArray());
        }

        return $query->get()->map(function (object $linha) {
            $minima = $linha->quantidade_minima !== null ? (float) $linha->quantidade_minima : null;
            $saldo = (float) $linha->saldo_atual;
            $linha->em_alerta = $minima !== null && $saldo < $minima;

            return $linha;
        });
    }

    // â”€â”€â”€ Helpers privados â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Resolve os IDs de unidade para o usuÃ¡rio.
     * Retorna null quando podeVerTodasUnidades (sem filtro); Collection quando Almoxarife.
     *
     * @return Collection<int, int>|null
     */
    private static function resolverUnidadeIds(User $usuario): ?Collection
    {
        if ($usuario->podeVerTodasUnidades()) {
            return null;
        }

        return $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');
    }

    /**
     * Executa a query de itens a repor, opcionalmente filtrando por unidadeIds.
     *
     * @param  array<int>|null  $unidadeIds  null = sem filtro (rede inteira)
     * @return Collection<int, object>
     */
    private static function queryItensARepor(?array $unidadeIds): Collection
    {
        $query = DB::table('estoque_minimos as em')
            ->join('unidades as u', function ($join) {
                $join->on('u.id', '=', 'em.unidade_id')
                    ->whereNull('u.deleted_at');
            })
            ->join('catalogo_itens as ci', function ($join) {
                $join->on('ci.id', '=', 'em.item_catalogo_id')
                    ->whereNull('ci.deleted_at')
                    ->where('ci.ativo', 1);
            })
            ->leftJoinSub(
                DB::table('saldos_estoque')
                    ->select('unidade_id', 'item_catalogo_id', DB::raw('SUM(quantidade) as saldo_total'))
                    ->whereNotNull('item_catalogo_id')
                    ->whereNull('fundido_para_id')
                    ->groupBy('unidade_id', 'item_catalogo_id'),
                's',
                function ($join) {
                    $join->on('s.unidade_id', '=', 'em.unidade_id')
                        ->on('s.item_catalogo_id', '=', 'em.item_catalogo_id');
                }
            )
            ->select([
                'em.unidade_id',
                'u.nome as unidade_nome',
                'em.item_catalogo_id',
                'ci.descricao as item_descricao',
                'ci.unidade_medida',
                'em.quantidade_minima',
                DB::raw('COALESCE(s.saldo_total, 0) as saldo_atual'),
            ])
            ->whereRaw('COALESCE(s.saldo_total, 0) < em.quantidade_minima')
            ->orderBy('u.nome')
            ->orderBy('ci.descricao');

        if ($unidadeIds !== null) {
            $query->whereIn('em.unidade_id', $unidadeIds);
        }

        return $query->get()->map(function (object $linha) {
            $minima = (float) $linha->quantidade_minima;
            $saldo = (float) $linha->saldo_atual;
            $linha->quantidade_sugerida = max(0.0, $minima - $saldo);

            return $linha;
        });
    }
}
