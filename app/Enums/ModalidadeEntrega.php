<?php

namespace App\Enums;

enum ModalidadeEntrega: string
{
    case Entrega = 'entrega';
    case Retirada = 'retirada';
    case Transportadora = 'transportadora';

    public function label(): string
    {
        return match ($this) {
            self::Entrega => 'Entrega pelo fornecedor',
            self::Retirada => 'Retirada pelo comprador',
            self::Transportadora => 'Via transportadora',
        };
    }
}
