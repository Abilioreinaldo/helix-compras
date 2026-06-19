<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\TransferenciaEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferirEstoqueAction
{
    /**
     * Transfere quantidade de um saldo de origem para a mesma identidade de item na unidade de
     * destino, num único movimento rastreável: saída na origem (pelo CMP vigente, que não muda)
     * + entrada no destino (recalcula o CMP do destino por média ponderada). O valor da rede é
     * conservado (origem perde, destino ganha o mesmo valor).
     *
     * Guards: Almoxarife da unidade de ORIGEM ou Admin; quantidade > 0; origem ≠ destino; saldo
     * suficiente. Item com `controla_lote` é BLOQUEADO no v1 (transferência por lote → v1.1-D).
     * Transação única (falha reverte tudo).
     *
     * @throws ValidationException
     */
    public function execute(SaldoEstoque $origem, Unidade $destino, float $quantidade, string $motivo, User $executadoPor): TransferenciaEstoque
    {
        if ($quantidade <= 0) {
            throw ValidationException::withMessages(['quantidade' => 'A quantidade da transferência deve ser maior que zero.']);
        }

        if ($origem->fundido_para_id !== null) {
            throw ValidationException::withMessages(['saldo' => 'O saldo de origem foi fundido — não pode ser transferido.']);
        }

        if ($origem->unidade_id === $destino->id) {
            throw ValidationException::withMessages(['destino' => 'A unidade de destino deve ser diferente da origem.']);
        }

        $almoxarifeOrigem = $executadoPor->unidades()
            ->withoutGlobalScopes()
            ->where('unidades.id', $origem->unidade_id)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->exists();

        if (! ($almoxarifeOrigem || $executadoPor->temPerfil(Perfil::Admin))) {
            throw ValidationException::withMessages([
                'autorizado' => 'Operação não permitida: usuário sem autorização para transferir deste saldo.',
            ]);
        }

        // Bloqueio v1: item com controle de lote (transferência por lote/FEFO → v1.1-D).
        if ($origem->controlaLote()) {
            throw ValidationException::withMessages([
                'controla_lote' => 'Transferência de item com controle de lote não é suportada no v1 (será tratada na v1.1-D).',
            ]);
        }

        return DB::transaction(function () use ($origem, $destino, $quantidade, $motivo, $executadoPor) {
            // Relock da origem por id (unidade/autorização já verificadas).
            $origem = SaldoEstoque::withoutGlobalScopes()->where('id', $origem->id)->lockForUpdate()->firstOrFail();

            $qtdOrigem = (float) $origem->quantidade;

            if ($quantidade > $qtdOrigem + 0.001) {
                throw ValidationException::withMessages([
                    'quantidade' => 'Saldo insuficiente na origem. Disponível: '.number_format($qtdOrigem, 3, ',', '.').'.',
                ]);
            }

            $cmpOrigem = (float) $origem->custo_medio_ponderado;
            $valorTransferido = $quantidade * $cmpOrigem;

            $destinoSaldo = $this->resolverSaldoDestino($origem, $destino);

            // Debita a origem pelo CMP vigente (CMP da origem NÃO muda).
            $qtdNovaOrigem = max(0.0, $qtdOrigem - $quantidade);
            $origem->update([
                'quantidade' => $qtdNovaOrigem,
                'valor_total' => $qtdNovaOrigem * $cmpOrigem,
            ]);

            // Credita o destino por média ponderada (recalcula o CMP do destino).
            $qtdDestAtual = (float) $destinoSaldo->quantidade;
            $valorDestAtual = (float) $destinoSaldo->valor_total;
            $qtdNovaDest = $qtdDestAtual + $quantidade;
            $valorNovoDest = $valorDestAtual + $valorTransferido;
            $novoCmpDest = $qtdNovaDest > 0 ? $valorNovoDest / $qtdNovaDest : 0;
            $destinoSaldo->update([
                'quantidade' => $qtdNovaDest,
                'custo_medio_ponderado' => $novoCmpDest,
                'valor_total' => $qtdNovaDest * $novoCmpDest,
            ]);

            $transferencia = TransferenciaEstoque::create([
                'saldo_origem_id' => $origem->id,
                'saldo_destino_id' => $destinoSaldo->id,
                'unidade_destino_id' => $destino->id,
                'quantidade' => $quantidade,
                'custo_unitario' => $cmpOrigem,
                'valor_total' => $valorTransferido,
                'motivo' => $motivo ?: null,
                'executado_por' => $executadoPor->id,
            ]);

            // Ledger append-only: uma movimentação na origem e uma no destino, ligadas à transferência.
            $this->registrarMovimentacao($origem->id, $transferencia->id, TipoMovimentacao::TransferenciaSaida, $quantidade, $cmpOrigem, $valorTransferido, $motivo, $executadoPor->id);
            $this->registrarMovimentacao($destinoSaldo->id, $transferencia->id, TipoMovimentacao::TransferenciaEntrada, $quantidade, $cmpOrigem, $valorTransferido, $motivo, $executadoPor->id);

            return $transferencia;
        });
    }

    /**
     * Resolve (ou cria) o saldo de destino com a mesma identidade do item da origem, na unidade
     * de destino e mesmo depósito. Identidade por catálogo quando vinculado; descrição quando avulso.
     */
    private function resolverSaldoDestino(SaldoEstoque $origem, Unidade $destino): SaldoEstoque
    {
        $catalogoId = $origem->item_catalogo_id;

        $base = SaldoEstoque::where('unidade_id', $destino->id)
            ->where('deposito', $origem->deposito)
            ->whereNull('fundido_para_id');

        if ($catalogoId !== null) {
            $base->where('item_catalogo_id', $catalogoId);
        } else {
            $base->where('descricao_normalizada', $origem->descricao_normalizada)
                ->whereNull('item_catalogo_id');
        }

        $saldo = (clone $base)->lockForUpdate()->first();

        if ($saldo !== null) {
            return $saldo;
        }

        try {
            return SaldoEstoque::create([
                'unidade_id' => $destino->id,
                'deposito' => $origem->deposito,
                'descricao_item' => $origem->descricao_item,
                'descricao_normalizada' => $origem->descricao_normalizada,
                'unidade_medida' => $origem->unidade_medida,
                'quantidade' => 0,
                'custo_medio_ponderado' => 0,
                'valor_total' => 0,
                'item_catalogo_id' => $catalogoId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Corrida: outra transferência criou o saldo de destino entre o SELECT e o INSERT.
            $saldo = (clone $base)->lockForUpdate()->first();

            if ($saldo === null) {
                throw $e;
            }

            return $saldo;
        }
    }

    private function registrarMovimentacao(int $saldoId, int $transferenciaId, TipoMovimentacao $tipo, float $quantidade, float $custoUnitario, float $valorTotal, string $motivo, int $registradoPor): void
    {
        MovimentacaoEstoque::create([
            'saldo_estoque_id' => $saldoId,
            'transferencia_estoque_id' => $transferenciaId,
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'custo_unitario' => $custoUnitario,
            'valor_total' => $valorTotal,
            'motivo' => $motivo ?: null,
            'registrado_por' => $registradoPor,
        ]);
    }
}
