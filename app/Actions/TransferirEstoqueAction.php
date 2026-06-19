<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\TransferenciaEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferirEstoqueAction
{
    /**
     * Transfere quantidade de um saldo de origem para a mesma identidade de item na unidade de
     * destino, num único movimento rastreável: saída na origem (pelo CMP vigente, que não muda)
     * + entrada no destino (recalcula o CMP do destino por média ponderada). O valor da rede é
     * conservado ao centavo (origem perde e destino ganha exatamente o mesmo valorTransferido).
     *
     * Guards: Almoxarife da unidade de ORIGEM ou Admin; quantidade > 0; origem ≠ destino; saldo
     * suficiente. Item com `controla_lote` (origem OU destino) é BLOQUEADO no v1 (→ v1.1-D).
     * Transação única (falha reverte tudo); lock canônico (menor id primeiro) evita deadlock.
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

        // Bloqueio v1: item com controle de lote na origem (transferência por lote/FEFO → v1.1-D).
        if ($origem->controlaLote()) {
            throw ValidationException::withMessages([
                'controla_lote' => 'Transferência de item com controle de lote não é suportada no v1 (será tratada na v1.1-D).',
            ]);
        }

        return DB::transaction(function () use ($origem, $destino, $quantidade, $motivo, $executadoPor) {
            // Identidade do saldo de destino (derivada do origem passado).
            $destinoExistenteId = $this->baseDestino($origem, $destino)->value('id');

            // Lock CANÔNICO (menor id primeiro) de origem + destino existente: ordem consistente
            // entre transferências concorrentes A↔B do mesmo item evita deadlock em MySQL.
            $idsLock = collect([$origem->id, $destinoExistenteId])->filter()->unique()->sort()->values()->all();
            SaldoEstoque::withoutGlobalScopes()->whereIn('id', $idsLock)->lockForUpdate()->get();

            // Re-lê a origem travada e re-valida que não virou tombstone entre o guard e o lock.
            $origem = SaldoEstoque::withoutGlobalScopes()
                ->whereKey($origem->id)
                ->whereNull('fundido_para_id')
                ->lockForUpdate()
                ->firstOrFail();

            $qtdOrigem = (float) $origem->quantidade;

            if ($quantidade > $qtdOrigem + 0.001) {
                throw ValidationException::withMessages([
                    'quantidade' => 'Saldo insuficiente na origem. Disponível: '.number_format($qtdOrigem, 3, ',', '.').'.',
                ]);
            }

            $destinoSaldo = $destinoExistenteId !== null
                ? SaldoEstoque::withoutGlobalScopes()->whereKey($destinoExistenteId)->lockForUpdate()->firstOrFail()
                : $this->criarSaldoDestino($origem, $destino);

            // Guard defensivo: destino que controla lote também bloqueia (a identidade por catálogo
            // normalmente torna isto redundante com o guard de origem, mas cobre catálogo editado).
            if ($destinoSaldo->controlaLote()) {
                throw ValidationException::withMessages([
                    'controla_lote' => 'O saldo de destino controla lote — transferência não suportada no v1.',
                ]);
            }

            $cmpOrigem = (float) $origem->custo_medio_ponderado;
            $valorTransferido = $quantidade * $cmpOrigem;

            // Debita a origem (CMP NÃO muda). valor_total por subtração direta do valorTransferido.
            $qtdNovaOrigem = max(0.0, $qtdOrigem - $quantidade);
            $origem->update([
                'quantidade' => $qtdNovaOrigem,
                'valor_total' => max(0.0, (float) $origem->valor_total - $valorTransferido),
            ]);

            // Credita o destino. valor_total por soma direta (conserva o valor da rede ao centavo);
            // CMP do destino recalculado por média ponderada.
            $qtdNovaDest = (float) $destinoSaldo->quantidade + $quantidade;
            $valorNovoDest = (float) $destinoSaldo->valor_total + $valorTransferido;
            $novoCmpDest = $qtdNovaDest > 0 ? $valorNovoDest / $qtdNovaDest : 0;
            $destinoSaldo->update([
                'quantidade' => $qtdNovaDest,
                'custo_medio_ponderado' => $novoCmpDest,
                'valor_total' => $valorNovoDest,
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
            // custo_unitario = CMP da ORIGEM nas duas (o destino só recalcula o próprio CMP do saldo).
            $this->registrarMovimentacao($origem->id, $transferencia->id, TipoMovimentacao::TransferenciaSaida, $quantidade, $cmpOrigem, $valorTransferido, $motivo, $executadoPor->id);
            $this->registrarMovimentacao($destinoSaldo->id, $transferencia->id, TipoMovimentacao::TransferenciaEntrada, $quantidade, $cmpOrigem, $valorTransferido, $motivo, $executadoPor->id);

            return $transferencia;
        });
    }

    /**
     * Query builder da identidade do saldo de destino (catálogo quando vinculado; descrição
     * normalizada quando avulso), na unidade de destino e mesmo depósito da origem.
     */
    private function baseDestino(SaldoEstoque $origem, Unidade $destino): Builder
    {
        $base = SaldoEstoque::query()
            ->where('unidade_id', $destino->id)
            ->where('deposito', $origem->deposito)
            ->whereNull('fundido_para_id');

        if ($origem->item_catalogo_id !== null) {
            $base->where('item_catalogo_id', $origem->item_catalogo_id);
        } else {
            $base->where('descricao_normalizada', $origem->descricao_normalizada)
                ->whereNull('item_catalogo_id');
        }

        return $base;
    }

    /**
     * Cria o saldo de destino zerado com a identidade do item da origem. Degrada para o existente
     * se uma transferência concorrente o criou (UNIQUE), em vez de duplicar/estourar.
     */
    private function criarSaldoDestino(SaldoEstoque $origem, Unidade $destino): SaldoEstoque
    {
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
                'item_catalogo_id' => $origem->item_catalogo_id,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $saldo = $this->baseDestino($origem, $destino)->lockForUpdate()->first();

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
