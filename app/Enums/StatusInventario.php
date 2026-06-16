<?php

namespace App\Enums;

/** Status possíveis de uma sessão de inventário. */
enum StatusInventario: string
{
    case EmAndamento = 'em_andamento';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::EmAndamento => 'Em Andamento',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }
}
