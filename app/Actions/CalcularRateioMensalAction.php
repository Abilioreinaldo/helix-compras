<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalcularRateioMensalAction
{
    /**
     * Calcula e persiste o rateio mensal do custo da central por unidade.
     *
     * Critério: consumo = % do gasto da unidade (SUM valor_total das SAÍDAS no mês) sobre
     * o gasto total da rede no mesmo mês. A alocação usa o método do maior resto (Hamilton)
     * em centavos inteiros: SUM(valor_rateado) == valorCentral exatamente, cada unidade no
     * piso ou piso+1 centavo (sem distorção/valor negativo).
     *
     * Documental: cria RateioCentral + RateioUnidade[] + uma MovimentacaoEstoque tipo
     * RateioCentral por unidade com valor > 0 (saldo_estoque_id null — não toca estoque).
     *
     * Idempotente: se já existe rateio para (mes, ano), retorna o existente sem duplicar
     * (inclusive sob corrida, via UNIQUE(mes,ano)). Guard: só Admin; período não pode ser
     * futuro; bloqueia quando a rede não teve consumo (não há base para ratear).
     *
     * @throws ValidationException
     */
    public function execute(int $mes, int $ano, float $valorCentral, User $criadoPor): RateioCentral
    {
        if (! $criadoPor->temPerfil(Perfil::Admin)) {
            throw ValidationException::withMessages([
                'autorizado' => 'Operação não permitida: apenas Admin pode executar o rateio da central.',
            ]);
        }

        if ($mes < 1 || $mes > 12) {
            throw ValidationException::withMessages(['mes' => 'Mês inválido (use 1 a 12).']);
        }

        if ($valorCentral <= 0) {
            throw ValidationException::withMessages(['valor_central' => 'O valor da central deve ser maior que zero.']);
        }

        $agora = Carbon::now();
        if ($ano > $agora->year || ($ano === $agora->year && $mes > $agora->month)) {
            throw ValidationException::withMessages(['periodo' => 'Não é possível ratear um período futuro.']);
        }

        // Idempotência (fast path): rateio do período já existe → devolve sem duplicar.
        $existente = RateioCentral::where('mes', $mes)->where('ano', $ano)->first();
        if ($existente !== null) {
            return $existente;
        }

        return DB::transaction(function () use ($mes, $ano, $valorCentral, $criadoPor) {
            $linhas = $this->calcularLinhas($mes, $ano, $valorCentral);

            try {
                $rateio = RateioCentral::create([
                    'mes' => $mes,
                    'ano' => $ano,
                    'valor_total' => $valorCentral,
                    'criado_por' => $criadoPor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Corrida: outro processo criou o rateio do período entre o check e o INSERT.
                // Idempotente: devolve o existente em vez de duplicar/estourar.
                return RateioCentral::where('mes', $mes)->where('ano', $ano)->firstOrFail();
            }

            foreach ($linhas as $linha) {
                $rateioUnidade = RateioUnidade::create([
                    'rateio_central_id' => $rateio->id,
                    'unidade_id' => $linha['unidade_id'],
                    'percentual_consumo' => $linha['percentual'],
                    'valor_rateado' => $linha['valor_rateado'],
                ]);

                // Movimentação documental só quando há valor a ratear (sem entradas de 0).
                if ($linha['valor_rateado'] > 0) {
                    MovimentacaoEstoque::create([
                        'saldo_estoque_id' => null,
                        'rateio_unidade_id' => $rateioUnidade->id,
                        'tipo' => TipoMovimentacao::RateioCentral,
                        'quantidade' => 0,
                        'custo_unitario' => 0,
                        'valor_total' => $linha['valor_rateado'],
                        'motivo' => "Rateio da central {$mes}/{$ano}.",
                        'registrado_por' => $criadoPor->id,
                    ]);
                }
            }

            return $rateio->load('unidades');
        });
    }

    /**
     * Computa percentual e valor rateado por unidade ativa pelo método do maior resto.
     *
     * @return array<int, array{unidade_id: int, consumo: float, percentual: float, valor_rateado: float}>
     *
     * @throws ValidationException quando a rede não teve consumo no período.
     */
    private function calcularLinhas(int $mes, int $ano, float $valorCentral): array
    {
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth()->endOfDay();

        // Consumo = SUM(valor_total) das saídas do mês, por unidade (via saldo). Junta unidades
        // e exclui soft-deletadas — simétrico ao fetch de unidades ativas abaixo. Intervalo de
        // datas é portável SQLite↔MySQL (sem MONTH()/strftime).
        $consumoPorUnidade = DB::table('movimentacoes_estoque as m')
            ->join('saldos_estoque as s', 's.id', '=', 'm.saldo_estoque_id')
            ->join('unidades as u', 'u.id', '=', 's.unidade_id')
            ->whereNull('u.deleted_at')
            ->where('m.tipo', TipoMovimentacao::Saida->value)
            ->whereBetween('m.created_at', [$inicio, $fim])
            ->groupBy('s.unidade_id')
            ->selectRaw('s.unidade_id as unidade_id, SUM(m.valor_total) as consumo')
            ->pluck('consumo', 'unidade_id');

        $total = (float) $consumoPorUnidade->sum();

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'consumo' => "Nenhuma unidade teve consumo (saídas) em {$mes}/{$ano} — não há base de consumo para ratear o custo da central.",
            ]);
        }

        $unidades = Unidade::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id')->pluck('id');

        return $this->alocarMaiorResto($unidades, $consumoPorUnidade, $total, $valorCentral);
    }

    /**
     * Método do maior resto (Hamilton) em centavos: pisos por proporção + 1 centavo às maiores
     * partes fracionárias. Garante SUM(valor_rateado) == valorCentral, sem valor negativo.
     *
     * @param  Collection<int, int>  $unidades
     * @param  Collection<int, mixed>  $consumoPorUnidade
     * @return array<int, array{unidade_id: int, consumo: float, percentual: float, valor_rateado: float}>
     */
    private function alocarMaiorResto($unidades, $consumoPorUnidade, float $total, float $valorCentral): array
    {
        $totalCentavos = (int) round($valorCentral * 100);

        $linhas = [];
        foreach ($unidades as $unidadeId) {
            $consumo = (float) ($consumoPorUnidade[$unidadeId] ?? 0);
            $exatoCentavos = ($consumo / $total) * $totalCentavos;
            $piso = (int) floor($exatoCentavos + 1e-6);

            $linhas[] = [
                'unidade_id' => (int) $unidadeId,
                'consumo' => $consumo,
                'percentual' => round($consumo / $total, 4),
                'centavos' => $piso,
                'resto' => $exatoCentavos - $piso,
            ];
        }

        // Distribui os centavos restantes às maiores partes fracionárias (desempate: maior consumo).
        $faltam = $totalCentavos - array_sum(array_column($linhas, 'centavos'));
        if ($faltam > 0) {
            $indices = array_keys($linhas);
            usort($indices, function ($a, $b) use ($linhas) {
                if ($linhas[$a]['resto'] === $linhas[$b]['resto']) {
                    return $linhas[$b]['consumo'] <=> $linhas[$a]['consumo'];
                }

                return $linhas[$b]['resto'] <=> $linhas[$a]['resto'];
            });

            for ($i = 0; $i < $faltam && $i < count($indices); $i++) {
                $linhas[$indices[$i]]['centavos']++;
            }
        }

        return array_map(fn (array $l): array => [
            'unidade_id' => $l['unidade_id'],
            'consumo' => $l['consumo'],
            'percentual' => $l['percentual'],
            'valor_rateado' => round($l['centavos'] / 100, 2),
        ], $linhas);
    }
}
