<?php

namespace App\Enums;

enum StatusPedidoCompra: string
{
    case Rascunho = 'rascunho';
    case Emitido = 'emitido';
    case Cancelado = 'cancelado';

    public function ehEditavel(): bool
    {
        return $this === self::Rascunho;
    }

    public function ehImutavel(): bool
    {
        return in_array($this, [self::Emitido, self::Cancelado]);
    }
}
