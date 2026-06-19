<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DescontoRateioAction
{
    /**
     * Reverte (credita) o rateio de uma unidade, criando uma MovimentacaoEstoque tipo
     * DescontoRateio com o valor rateado. Documental — não toca estoque.
     *
     * Guard: só Admin. A linha precisa pertencer ao rateio informado e ainda não ter sido
     * revertida (não duplica reversa). O motivo é obrigatório e fica auditável no ledger.
     *
     * @throws ValidationException
     */
    public function execute(RateioCentral $rateio, RateioUnidade $item, string $motivo, User $registradoPor): MovimentacaoEstoque
    {
        if (! $registradoPor->temPerfil(Perfil::Admin)) {
            throw ValidationException::withMessages([
                'autorizado' => 'Operação não permitida: apenas Admin pode reverter rateio.',
            ]);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo da reversão.']);
        }

        if ($item->rateio_central_id !== $rateio->id) {
            throw ValidationException::withMessages([
                'item' => 'A linha de rateio não pertence a este rateio.',
            ]);
        }

        return DB::transaction(function () use ($item, $motivo, $registradoPor) {
            // Relock + reverifica idempotência sob lock: não reverter duas vezes.
            $linha = RateioUnidade::where('id', $item->id)->lockForUpdate()->firstOrFail();

            if ($linha->foiRevertido()) {
                throw ValidationException::withMessages([
                    'item' => 'Este rateio de unidade já foi revertido.',
                ]);
            }

            return MovimentacaoEstoque::create([
                'saldo_estoque_id' => null,
                'rateio_unidade_id' => $linha->id,
                'tipo' => TipoMovimentacao::DescontoRateio,
                'quantidade' => 0,
                'custo_unitario' => 0,
                'valor_total' => $linha->valor_rateado,
                'motivo' => $motivo,
                'registrado_por' => $registradoPor->id,
            ]);
        });
    }
}
