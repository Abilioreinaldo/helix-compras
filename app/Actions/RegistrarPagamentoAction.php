<?php

namespace App\Actions;

use App\Enums\MetodoPagamento;
use App\Enums\StatusPagamento;
use App\Models\Banco;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Registra o pagamento de uma conta a pagar (total ou parcial).
 */
class RegistrarPagamentoAction
{
    /** Tolerância sobre o total devido (juros/multa de última hora). */
    private const TOLERANCIA = 1.10;

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

            $teto = round($totalDevido * self::TOLERANCIA, 2);
            if (round($valorPago, 2) > $teto) {
                throw ValidationException::withMessages([
                    'valorPago' => 'O valor pago não pode exceder o total devido + 10% (máx. R$ '.number_format($teto, 2, ',', '.').').',
                ]);
            }

            $valorPago = round($valorPago, 2);
            $status = $valorPago >= round($totalDevido, 2)
                ? StatusPagamento::Pago
                : StatusPagamento::Parcial;

            $pagamento->update([
                'valor_pago' => $valorPago,
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
                'valor_pago' => $valorPago,
                'status' => $status->value,
                'por' => $usuario->id,
            ]);

            return $pagamento->fresh();
        });
    }
}
