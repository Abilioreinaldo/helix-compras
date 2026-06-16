<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AjusteEstoqueAction
{
    /**
     * Registra um ajuste de inventário.
     * Ajuste NÃO altera o CMP — valoriza/reduz pela CMP vigente.
     * Ajuste positivo soma quantidade; ajuste negativo subtrai.
     *
     * @throws ValidationException
     */
    public function execute(
        SaldoEstoque $saldo,
        TipoMovimentacao $tipo,
        float $quantidade,
        string $motivo,
        User $registradoPor,
    ): MovimentacaoEstoque {
        if (! in_array($tipo, [TipoMovimentacao::AjustePositivo, TipoMovimentacao::AjusteNegativo])) {
            throw ValidationException::withMessages([
                'tipo' => 'Tipo inválido para ajuste. Use ajuste_positivo ou ajuste_negativo.',
            ]);
        }

        if ($quantidade <= 0) {
            throw ValidationException::withMessages([
                'quantidade' => 'A quantidade de ajuste deve ser maior que zero.',
            ]);
        }

        // Autorizado: Admin (irrestrito) ou Almoxarife da unidade do saldo.
        // ESCOPO: "Ajuste — inventário/correção, só Admin ou Almoxarife".
        $autorizado = $registradoPor->temPerfil(Perfil::Admin)
            || $registradoPor->unidades()->withoutGlobalScopes()
                ->where('unidades.id', $saldo->unidade_id)
                ->wherePivot('perfil', Perfil::Almoxarife->value)
                ->exists();

        if (! $autorizado) {
            throw ValidationException::withMessages([
                'saldo' => 'Operação não permitida: usuário sem autorização para ajuste neste saldo.',
            ]);
        }

        return DB::transaction(function () use ($saldo, $tipo, $quantidade, $motivo, $registradoPor) {
            // withoutGlobalScopes: relocking by id — unidade já foi verificada acima
            $saldo = SaldoEstoque::withoutGlobalScopes()->where('id', $saldo->id)->lockForUpdate()->firstOrFail();

            $cmpVigente = (float) $saldo->custo_medio_ponderado;
            $qtdAtual = (float) $saldo->quantidade;

            if ($tipo === TipoMovimentacao::AjusteNegativo && $quantidade > $qtdAtual + 0.001) {
                throw ValidationException::withMessages([
                    'quantidade' => 'Ajuste negativo excede o saldo disponível ('.number_format($qtdAtual, 3, ',', '.').').',
                ]);
            }

            $qtdNova = $tipo->adicionaEstoque()
                ? $qtdAtual + $quantidade
                // Clamp to 0: a tolerância de 0.001 permite passar quantidades marginalmente
                // acima do saldo para evitar falsos positivos de ponto flutuante.
                : max(0.0, $qtdAtual - $quantidade);

            $saldo->update([
                'quantidade' => $qtdNova,
                // CMP não se altera em ajuste
                'valor_total' => $qtdNova * $cmpVigente,
            ]);

            return MovimentacaoEstoque::create([
                'saldo_estoque_id' => $saldo->id,
                'item_recebimento_id' => null,
                'item_pedido_compra_id' => null,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'custo_unitario' => $cmpVigente,
                'valor_total' => $quantidade * $cmpVigente,
                'motivo' => $motivo,
                'registrado_por' => $registradoPor->id,
            ]);
        });
    }
}
