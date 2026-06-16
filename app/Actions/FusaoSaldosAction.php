<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\SaldoFusaoLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FusaoSaldosAction
{
    /**
     * Funde saldos de estoque em um unico saldo destino (menor id).
     * Origens viram tombstones. Ledger append-only. CMP com bcmath.
     *
     * @param  Collection<int, SaldoEstoque>|array<int, SaldoEstoque>  $saldos
     *
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function fundir(Collection|array $saldos, User $executadoPor): SaldoEstoque
    {
        abort_unless($executadoPor->temPerfil(Perfil::Admin), 403);

        $saldos = $saldos instanceof Collection ? $saldos : collect($saldos);

        if ($saldos->count() < 2) {
            throw ValidationException::withMessages([
                'saldos' => 'A fusao requer ao menos dois saldos.',
            ]);
        }

        return DB::transaction(function () use ($saldos, $executadoPor) {
            $ids = $saldos->pluck('id')->sort()->values();
            $locked = SaldoEstoque::withoutGlobalScopes()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $semCatalogo = $locked->filter(fn (SaldoEstoque $s) => $s->item_catalogo_id === null);
            if ($semCatalogo->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'saldos' => 'Todos os saldos devem estar vinculados ao catalogo de itens para ser fundidos.',
                ]);
            }

            if ($locked->pluck('unidade_id')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'saldos' => 'Todos os saldos devem pertencer a mesma unidade para ser fundidos.',
                ]);
            }

            if ($locked->pluck('deposito')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'saldos' => 'Todos os saldos devem pertencer ao mesmo deposito para ser fundidos.',
                ]);
            }

            if ($locked->pluck('item_catalogo_id')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'saldos' => 'Todos os saldos devem estar vinculados ao mesmo item de catalogo para ser fundidos.',
                ]);
            }

            $jaFundidos = $locked->filter(fn (SaldoEstoque $s) => $s->fundido_para_id !== null);
            if ($jaFundidos->count() === $locked->count()) {
                $destino = SaldoEstoque::withoutGlobalScopes()->find($locked->first()->fundido_para_id);

                return $destino ?? $locked->first();
            }

            if ($jaFundidos->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'saldos' => 'Um ou mais saldos ja foram fundidos. Filtre apenas saldos ativos.',
                ]);
            }

            $destino = $locked->sortBy('id')->first();
            $origens = $locked->sortBy('id')->skip(1)->values();

            $somaQtd = '0';
            $somaValor = '0';

            foreach ($origens as $origem) {
                $qtd = number_format((float) $origem->quantidade, 10, '.', '');
                $valor = number_format((float) $origem->valor_total, 10, '.', '');
                $cmp = number_format((float) $origem->custo_medio_ponderado, 10, '.', '');

                SaldoFusaoLog::create([
                    'saldo_destino_id' => $destino->id,
                    'saldo_origem_id' => $origem->id,
                    'quantidade_origem' => (float) $origem->quantidade,
                    'cmp_origem' => (float) $origem->custo_medio_ponderado,
                    'valor_total_origem' => (float) $origem->valor_total,
                    'item_catalogo_id_origem' => $origem->item_catalogo_id,
                    'descricao_normalizada_origem' => $origem->descricao_normalizada,
                    'deposito_origem' => $origem->deposito,
                    'unidade_id_origem' => $origem->unidade_id,
                    'executado_por' => $executadoPor->id,
                ]);

                MovimentacaoEstoque::create([
                    'saldo_estoque_id' => $origem->id,
                    'item_recebimento_id' => null,
                    'item_pedido_compra_id' => null,
                    'tipo' => TipoMovimentacao::Fusao,
                    'quantidade' => (float) $origem->quantidade,
                    'custo_unitario' => (float) $origem->custo_medio_ponderado,
                    'valor_total' => (float) $origem->valor_total,
                    'motivo' => 'Fusao: saldo origem #'.$origem->id.' fundido ao destino #'.$destino->id.'.',
                    'registrado_por' => $executadoPor->id,
                ]);

                $origem->update([
                    'quantidade' => 0,
                    'valor_total' => 0,
                    'fundido_para_id' => $destino->id,
                    'fundido_em' => now(),
                ]);

                $somaQtd = bcadd($somaQtd, $qtd, 10);
                $somaValor = bcadd($somaValor, $valor, 10);
            }

            $qtdDestinoAntes = number_format((float) $destino->quantidade, 10, '.', '');
            $valorDestinoAntes = number_format((float) $destino->valor_total, 10, '.', '');

            $qtdTotal = bcadd($qtdDestinoAntes, $somaQtd, 10);
            $valorTotal = bcadd($valorDestinoAntes, $somaValor, 10);

            if (bccomp($qtdTotal, '0', 10) > 0) {
                $cmpRaw = bcdiv($valorTotal, $qtdTotal, 10);
                $novoCmp = (string) round((float) $cmpRaw, 4);
            } else {
                $novoCmp = (string) $destino->custo_medio_ponderado;
            }

            $valorFinal = (string) round((float) $qtdTotal * (float) $novoCmp, 2);

            $desvio = abs((float) $valorFinal - (float) $qtdTotal * (float) $novoCmp);
            if ($desvio >= 0.01) {
                throw new \RuntimeException('Erro de precisao na fusao: desvio de R$ '.$desvio);
            }

            // Custo unitario do agregado que ENTRA no destino (somente as origens), para que
            // a movimentacao de Fusao satisfaca quantidade x custo_unitario ~= valor_total.
            // Diferente do CMP final do saldo destino (novoCmp), que pondera destino + origens.
            $cmpAgregadoOrigens = bccomp($somaQtd, '0', 10) > 0
                ? (string) round((float) bcdiv($somaValor, $somaQtd, 10), 4)
                : '0';

            MovimentacaoEstoque::create([
                'saldo_estoque_id' => $destino->id,
                'item_recebimento_id' => null,
                'item_pedido_compra_id' => null,
                'tipo' => TipoMovimentacao::Fusao,
                'quantidade' => (float) $somaQtd,
                'custo_unitario' => (float) $cmpAgregadoOrigens,
                'valor_total' => (float) $somaValor,
                'motivo' => 'Fusao: recebimento do agregado de '.$origens->count().' saldo(s) origem.',
                'registrado_por' => $executadoPor->id,
            ]);

            $destino->update([
                'quantidade' => (float) $qtdTotal,
                'custo_medio_ponderado' => (float) $novoCmp,
                'valor_total' => (float) $valorFinal,
            ]);

            return $destino->refresh();
        });
    }
}
