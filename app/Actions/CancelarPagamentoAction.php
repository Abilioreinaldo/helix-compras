<?php

namespace App\Actions;

use App\Enums\StatusPagamento;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cancela uma conta a pagar. Mantém o histórico (não exclui) — registra o motivo.
 */
class CancelarPagamentoAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Pagamento $pagamento, string $motivo, User $usuario): Pagamento
    {
        if (blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo do cancelamento.']);
        }

        return DB::transaction(function () use ($pagamento, $motivo, $usuario) {
            $pagamento = Pagamento::lockForUpdate()->findOrFail($pagamento->id);

            if ($pagamento->status === StatusPagamento::Pago) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Um pagamento já pago não pode ser cancelado.',
                ]);
            }

            if ($pagamento->status === StatusPagamento::Cancelado) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Este pagamento já está cancelado.',
                ]);
            }

            $pagamento->update([
                'status' => StatusPagamento::Cancelado,
                'observacoes' => $motivo,
                'atualizado_por' => $usuario->id,
            ]);

            Log::warning('Pagamento cancelado.', [
                'pagamento_id' => $pagamento->id,
                'motivo' => $motivo,
                'por' => $usuario->id,
            ]);

            return $pagamento->fresh();
        });
    }
}
