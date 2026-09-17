<?php

namespace App\Actions;

use App\Enums\MetodoPagamento;
use App\Enums\StatusPagamento;
use App\Models\Banco;
use App\Models\Pagamento;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Registra o pagamento de uma conta a pagar (total ou parcial).
 */
class RegistrarPagamentoAction
{
    /**
     * @throws ValidationException
     */
    public function execute(
        Pagamento $pagamento,
        float $valorPago,
        string $dataPagamento,
        MetodoPagamento $metodo,
        ?Banco $banco,
        ?string $referenciaBanco,
        ?string $numeroCheque,
        User $usuario,
    ): Pagamento {
        if ($valorPago <= 0) {
            throw ValidationException::withMessages(['valorPago' => 'O valor pago deve ser maior que zero.']);
        }

        if (Carbon::parse($dataPagamento)->startOfDay()->isAfter(Carbon::today())) {
            throw ValidationException::withMessages(['dataPagamento' => 'A data de pagamento não pode ser futura.']);
        }

        if ($metodo === MetodoPagamento::Cheque && blank($numeroCheque)) {
            throw ValidationException::withMessages(['numeroCheque' => 'Informe o número do cheque.']);
        }

        // Lock pessimista: evita duplo registro por dois operadores simultâneos.
        return DB::transaction(function () use ($pagamento, $valorPago, $dataPagamento, $metodo, $banco, $referenciaBanco, $numeroCheque, $usuario) {
            $pagamento = Pagamento::lockForUpdate()->findOrFail($pagamento->id);

            if (in_array($pagamento->status, [StatusPagamento::Pago, StatusPagamento::Cancelado], true)) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Este pagamento já está '.$pagamento->status->rotulo().' e não pode ser registrado.',
                ]);
            }

            // Total efetivamente devido (com juros/multa/desconto) — base do teto e do status.
            $totalDevido = $pagamento->calcularTotal();

            // COMPRAS-V2 (4ª auditoria): o lançamento SOMA ao que já foi pago (lido sob o lock
            // acima). Antes, `valor_pago` era sobrescrito: 600 + 400 virava 400/parcial, e o
            // teto de 110% valia por lançamento — cabiam 2.100 numa dívida de 1.000.
            $valorLancamento = round($valorPago, 2);
            $jaPago = round((float) $pagamento->valor_pago, 2);
            $acumulado = round($jaPago + $valorLancamento, 2);

            $teto = round($totalDevido * (float) config('compras.pagamento.teto_total_pago', 1.10), 2);
            if ($acumulado > $teto) {
                $cabe = max(0.0, round($teto - $jaPago, 2));
                throw ValidationException::withMessages([
                    'valorPago' => 'O total pago não pode exceder o total devido mais a tolerância (máx. R$ '.number_format($teto, 2, ',', '.')
                        .'). Já pago: R$ '.number_format($jaPago, 2, ',', '.').'; este lançamento pode ser de no máximo R$ '.number_format($cabe, 2, ',', '.').'.',
                ]);
            }

            $status = $acumulado >= round($totalDevido, 2)
                ? StatusPagamento::Pago
                : StatusPagamento::Parcial;

            $pagamento->update([
                'valor_pago' => $acumulado,
                'data_pagamento' => Carbon::parse($dataPagamento)->toDateString(),
                'metodo_pagamento' => $metodo,
                'banco_id' => $metodo->exigeBanco() ? $banco?->id : null,
                'referencia_banco' => $referenciaBanco ?: null,
                'numero_cheque' => $metodo === MetodoPagamento::Cheque ? $numeroCheque : null,
                'status' => $status,
                'atualizado_por' => $usuario->id,
            ]);

            Log::info('Pagamento registrado.', [
                'pagamento_id' => $pagamento->id,
                'valor_lancamento' => $valorLancamento,
                'valor_pago_acumulado' => $acumulado,
                'status' => $status->value,
                'por' => $usuario->id,
            ]);

            // ESCOPO (D10, ponte dual): dual-write da foundation.
            app(ActivityRecorder::class)->record('compras.pagamento_registrado', $pagamento, $pagamento->tenant_id, [
                'actor_id' => $usuario->id,
                // Sem tabela de baixas, a trilha de auditoria É o histórico de cada lançamento
                // parcial (valor, data, método e referência de CADA baixa).
                'metadata' => [
                    'valor_lancamento' => $valorLancamento,
                    'valor_pago_anterior' => $jaPago,
                    'valor_pago_acumulado' => $acumulado,
                    'total_devido' => $totalDevido,
                    'data_pagamento' => Carbon::parse($dataPagamento)->toDateString(),
                    'metodo' => $metodo->value,
                    'referencia_banco' => $referenciaBanco ?: null,
                    'numero_cheque' => $metodo === MetodoPagamento::Cheque ? $numeroCheque : null,
                    'status' => $status->value,
                ],
            ]);

            return $pagamento->fresh();
        });
    }
}
