<?php

namespace App\Enums;

enum TipoMovimentacao: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case AjustePositivo = 'ajuste_positivo';
    case AjusteNegativo = 'ajuste_negativo';
    /**
     * Fusão: movimentação documental gerada pela FusaoSaldosAction.
     * O ajuste de saldo é feito explicitamente pela action — este tipo
     * apenas documenta o evento no ledger append-only.
     */
    case Fusao = 'fusao';
    /**
     * Rateio da central: registro FINANCEIRO documental do custo central rateado
     * para uma unidade (CalcularRateioMensalAction). Não toca saldo de estoque
     * (saldo_estoque_id null); o valor por unidade vive em rateio_unidades.
     */
    case RateioCentral = 'rateio_central';
    /**
     * Desconto de rateio: reversa/crédito de um rateio (DescontoRateioAction).
     * Documental, financeiro — não muta estoque.
     */
    case DescontoRateio = 'desconto_rateio';
    /**
     * Transferência entre unidades (TransferirEstoqueAction): saída no saldo de origem.
     * Movimento de estoque real (debita origem) — pareado com TransferenciaEntrada.
     */
    case TransferenciaSaida = 'transferencia_saida';
    /**
     * Transferência entre unidades: entrada no saldo de destino (credita, recalcula CMP).
     */
    case TransferenciaEntrada = 'transferencia_entrada';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::AjustePositivo => 'Ajuste (+)',
            self::AjusteNegativo => 'Ajuste (−)',
            self::Fusao => 'Fusão',
            self::RateioCentral => 'Rateio da Central',
            self::DescontoRateio => 'Desconto de Rateio',
            self::TransferenciaSaida => 'Transferência (saída)',
            self::TransferenciaEntrada => 'Transferência (entrada)',
        };
    }

    /** Direção no saldo: true = soma, false = subtrai. */
    public function adicionaEstoque(): bool
    {
        return match ($this) {
            self::Entrada, self::AjustePositivo, self::TransferenciaEntrada => true,
            self::Saida, self::AjusteNegativo, self::TransferenciaSaida => false,
            // Tipos documentais — não participam da math de saldo de estoque. A FusaoSaldosAction
            // ajusta saldos explicitamente; rateio/desconto são puramente financeiros (sem saldo).
            self::Fusao, self::RateioCentral, self::DescontoRateio => throw new \LogicException(
                "TipoMovimentacao::{$this->name} é documental — não ajusta saldo de estoque."
            ),
        };
    }

    /** Tipos documentais/financeiros que não mutam saldo de estoque. */
    public function ehDocumental(): bool
    {
        return match ($this) {
            self::Fusao, self::RateioCentral, self::DescontoRateio => true,
            default => false,
        };
    }
}
