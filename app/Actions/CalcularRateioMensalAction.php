<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalcularRateioMensalAction
{
    /**
     * Calcula e persiste o rateio mensal do custo da central por unidade.
     *
     * Critério: consumo = % do gasto da unidade (SUM valor_total das SAÍDAS no mês) sobre
     * o gasto total da rede no mesmo mês. valor_rateado = percentual × valorCentral, com o
     * resíduo de arredondamento alocado à unidade de maior consumo (sum == valorCentral).
     *
     * Documental: cria RateioCentral + RateioUnidade[] + uma MovimentacaoEstoque tipo
     * RateioCentral por unidade rateada (saldo_estoque_id null — não toca estoque).
     *
     * Idempotente: se já existe rateio para (mes, ano), retorna o existente sem duplicar.
     * Guard: só Admin; período não pode ser futuro. Transação única (falha reverte tudo).
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

        // Idempotência: rateio do período já existe → devolve sem duplicar.
        $existente = RateioCentral::where('mes', $mes)->where('ano', $ano)->first();
        if ($existente !== null) {
            return $existente;
        }

        return DB::transaction(function () use ($mes, $ano, $valorCentral, $criadoPor) {
            $linhas = $this->calcularLinhas($mes, $ano, $valorCentral);

            $rateio = RateioCentral::create([
                'mes' => $mes,
                'ano' => $ano,
                'valor_total' => $valorCentral,
                'criado_por' => $criadoPor->id,
            ]);

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
     * Computa percentual e valor rateado por unidade ativa, com resíduo na maior consumidora.
     *
     * @return array<int, array{unidade_id: int, consumo: float, percentual: float, valor_rateado: float}>
     */
    private function calcularLinhas(int $mes, int $ano, float $valorCentral): array
    {
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth()->endOfDay();

        // Consumo = SUM(valor_total) das saídas do mês, por unidade (via saldo). Intervalo de
        // datas é portável SQLite↔MySQL (sem MONTH()/strftime).
        $consumoPorUnidade = DB::table('movimentacoes_estoque as m')
            ->join('saldos_estoque as s', 's.id', '=', 'm.saldo_estoque_id')
            ->where('m.tipo', TipoMovimentacao::Saida->value)
            ->whereBetween('m.created_at', [$inicio, $fim])
            ->groupBy('s.unidade_id')
            ->selectRaw('s.unidade_id as unidade_id, SUM(m.valor_total) as consumo')
            ->pluck('consumo', 'unidade_id');

        $total = (float) $consumoPorUnidade->sum();

        // Todas as unidades ativas da rede (Admin é network-wide).
        $unidades = Unidade::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id')->pluck('id');

        $linhas = [];
        foreach ($unidades as $unidadeId) {
            $consumo = (float) ($consumoPorUnidade[$unidadeId] ?? 0);
            $percentual = $total > 0 ? round($consumo / $total, 4) : 0.0;
            $valorRateado = $total > 0 ? round($percentual * $valorCentral, 2) : 0.0;

            $linhas[] = [
                'unidade_id' => (int) $unidadeId,
                'consumo' => $consumo,
                'percentual' => $percentual,
                'valor_rateado' => $valorRateado,
            ];
        }

        return $this->alocarResiduo($linhas, $valorCentral, $total);
    }

    /**
     * Aloca o resíduo de arredondamento (valorCentral − SUM valor_rateado) à unidade de maior
     * consumo, garantindo SUM(valor_rateado) == valorCentral quando há consumo na rede.
     *
     * @param  array<int, array{unidade_id: int, consumo: float, percentual: float, valor_rateado: float}>  $linhas
     * @return array<int, array{unidade_id: int, consumo: float, percentual: float, valor_rateado: float}>
     */
    private function alocarResiduo(array $linhas, float $valorCentral, float $total): array
    {
        if ($total <= 0 || $linhas === []) {
            return $linhas;
        }

        $somaRateada = round(array_sum(array_column($linhas, 'valor_rateado')), 2);
        $residuo = round($valorCentral - $somaRateada, 2);

        if (abs($residuo) < 0.005) {
            return $linhas;
        }

        // Índice da unidade de maior consumo (desempate: maior valor_rateado).
        $maiorIdx = 0;
        foreach ($linhas as $i => $linha) {
            if ($linha['consumo'] > $linhas[$maiorIdx]['consumo']) {
                $maiorIdx = $i;
            }
        }

        $linhas[$maiorIdx]['valor_rateado'] = round($linhas[$maiorIdx]['valor_rateado'] + $residuo, 2);

        return $linhas;
    }
}
