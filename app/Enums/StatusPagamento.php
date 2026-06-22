<?php

namespace App\Enums;

/** Situação de uma conta a pagar. */
enum StatusPagamento: string
{
    case Pendente = 'pendente';
    case Agendado = 'agendado';
    case Pago = 'pago';
    case Vencido = 'vencido';
    case Cancelado = 'cancelado';
    case Parcial = 'parcial';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Agendado => 'Agendado',
            self::Pago => 'Pago',
            self::Vencido => 'Vencido',
            self::Cancelado => 'Cancelado',
            self::Parcial => 'Parcial',
        };
    }

    /** Estados em que o pagamento ainda está em aberto (deve/pode ser pago). */
    public function emAberto(): bool
    {
        return in_array($this, [self::Pendente, self::Agendado, self::Vencido, self::Parcial], true);
    }
}
