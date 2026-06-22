<?php

namespace App\Actions;

use App\Enums\StatusPagamento;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Agenda um pagamento para uma data futura (ou hoje).
 */
class AgendarPagamentoAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Pagamento $pagamento, string $data, User $usuario): Pagamento
    {
        if (Carbon::parse($data)->startOfDay()->isBefore(Carbon::today())) {
            throw ValidationException::withMessages(['data' => 'A data de agendamento não pode ser no passado.']);
        }

        return DB::transaction(function () use ($pagamento, $data, $usuario) {
            $pagamento = Pagamento::lockForUpdate()->findOrFail($pagamento->id);

            if (in_array($pagamento->status, [StatusPagamento::Pago, StatusPagamento::Cancelado], true)) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Pagamento '.$pagamento->status->rotulo().' não pode ser agendado.',
                ]);
            }

            $pagamento->update([
                'agendado_para' => Carbon::parse($data)->toDateString(),
                'status' => StatusPagamento::Agendado,
                'atualizado_por' => $usuario->id,
            ]);

            Log::info('Pagamento agendado.', [
                'pagamento_id' => $pagamento->id,
                'agendado_para' => Carbon::parse($data)->toDateString(),
                'por' => $usuario->id,
            ]);

            return $pagamento->fresh();
        });
    }
}
