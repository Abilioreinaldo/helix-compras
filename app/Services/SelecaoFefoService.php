<?php

namespace App\Services;

use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use Illuminate\Database\Eloquent\Collection;

class SelecaoFefoService
{
    /**
     * Retorna os lotes VIVOS do saldo em ordem FEFO (First Expiry First Out).
     *
     * Leitura pura: não trava, não muta nada — apenas ordena para consumo posterior
     * (a baixa física é responsabilidade da SaidaEstoqueAction, Passo 4).
     *
     * Ordenação portável SQLite↔MySQL (nenhum dos dois suporta NULLS LAST):
     * - `validade IS NULL` ASC → lotes com validade primeiro (0), sem validade por último (1);
     * - `validade` ASC → menor validade (vence antes) sai primeiro;
     * - `id` ASC → desempate estável por ordem de criação.
     *
     * `validade IS NULL` avalia para 0/1 igual nos dois dialetos, então NÃO precisa de
     * ramo DB::getDriverName(). Sem `julianday`/`DATE`/`DATEDIFF` — comparação é date puro.
     *
     * Filtro estrito por `saldo_estoque_id` via relação `lotesVivos()` (que já aplica
     * `fundido_para_id IS NULL`): tombstones de fusão e lotes de outros saldos ficam fora.
     *
     * @return Collection<int, LoteEstoque>
     */
    public function lotesPorOrdemFefo(SaldoEstoque $saldo): Collection
    {
        return $saldo->lotesVivos()
            ->orderByRaw('validade IS NULL')
            ->orderBy('validade')
            ->orderBy('id')
            ->get();
    }
}
