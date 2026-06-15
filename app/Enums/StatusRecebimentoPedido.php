<?php

namespace App\Enums;

enum StatusRecebimentoPedido: string
{
    case Pendente = 'pendente';
    case Parcial = 'parcial';
    case Total = 'total';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Parcial => 'Recebido Parcialmente',
            self::Total => 'Recebido Totalmente',
        };
    }
}
