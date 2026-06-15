<?php

namespace App\Enums;

enum TipoMovimentacao: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case AjustePositivo = 'ajuste_positivo';
    case AjusteNegativo = 'ajuste_negativo';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::AjustePositivo => 'Ajuste (+)',
            self::AjusteNegativo => 'Ajuste (−)',
        };
    }

    /** Direção no saldo: true = soma, false = subtrai. */
    public function adicionaEstoque(): bool
    {
        return match ($this) {
            self::Entrada, self::AjustePositivo => true,
            self::Saida, self::AjusteNegativo => false,
        };
    }
}
