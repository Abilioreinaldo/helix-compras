<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaidaEstoqueAction
{
    /**
     * Registra uma saída de estoque pelo CMP vigente (não altera o CMP).
     *
     * @throws ValidationException
     */
    public function execute(
        SaldoEstoque $saldo,
        float $quantidade,
        string $motivo,
        User $registradoPor,
    ): MovimentacaoEstoque {
        if ($quantidade <= 0) {
            throw ValidationException::withMessages([
                'quantidade' => 'A quantidade de saída deve ser maior que zero.',
            ]);
        }

        if (! $registradoPor->unidades()->withoutGlobalScopes()
            ->where('unidades.id', $saldo->unidade_id)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'saldo' => 'Operação não permitida: almoxarife não pertence à unidade deste saldo.',
            ]);
        }

        return DB::transaction(function () use ($saldo, $quantidade, $motivo, $registradoPor) {
            // withoutGlobalScopes: relocking by id — unidade já foi verificada acima
            $saldo = SaldoEstoque::withoutGlobalScopes()->where('id', $saldo->id)->lockForUpdate()->firstOrFail();

            $qtdDisponivel = (float) $saldo->quantidade;

            if ($quantidade > $qtdDisponivel + 0.001) {
                throw ValidationException::withMessages([
                    'quantidade' => 'Saldo insuficiente. Disponível: '.number_format($qtdDisponivel, 3, ',', '.').'.',
                ]);
            }

            $cmpVigente = (float) $saldo->custo_medio_ponderado;
            // Clamp to 0: a tolerância de 0.001 permite passar quantidades marginalmente
            // acima do saldo para evitar falsos positivos de ponto flutuante.
            $qtdNova = max(0.0, $qtdDisponivel - $quantidade);

            $saldo->update([
                'quantidade' => $qtdNova,
                // CMP não se altera na saída
                'valor_total' => $qtdNova * $cmpVigente,
            ]);

            return MovimentacaoEstoque::create([
                'saldo_estoque_id' => $saldo->id,
                'item_recebimento_id' => null,
                'item_pedido_compra_id' => null,
                'tipo' => TipoMovimentacao::Saida,
                'quantidade' => $quantidade,
                'custo_unitario' => $cmpVigente,
                'valor_total' => $quantidade * $cmpVigente,
                'motivo' => $motivo,
                'registrado_por' => $registradoPor->id,
            ]);
        });
    }
}
