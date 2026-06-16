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

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::AjustePositivo => 'Ajuste (+)',
            self::AjusteNegativo => 'Ajuste (−)',
            self::Fusao => 'Fusão',
        };
    }

    /** Direção no saldo: true = soma, false = subtrai. */
    public function adicionaEstoque(): bool
    {
        return match ($this) {
            self::Entrada, self::AjustePositivo => true,
            self::Saida, self::AjusteNegativo => false,
            // Fusão é documental — o saldo é ajustado explicitamente pela FusaoSaldosAction.
            // Este método não é chamado para Fusao; lança exceção se for acidentalmente invocado.
            self::Fusao => throw new \LogicException('TipoMovimentacao::Fusao é documental — use FusaoSaldosAction para ajustar saldos.'),
        };
    }
}
