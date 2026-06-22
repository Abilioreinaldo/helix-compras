<?php

namespace App\Enums;

/** Forma de pagamento de uma conta a pagar. */
enum MetodoPagamento: string
{
    case Boleto = 'boleto';
    case Transferencia = 'transferencia';
    case Cartao = 'cartao';
    case Cheque = 'cheque';
    case Dinheiro = 'dinheiro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Boleto => 'Boleto',
            self::Transferencia => 'Transferência',
            self::Cartao => 'Cartão',
            self::Cheque => 'Cheque',
            self::Dinheiro => 'Dinheiro',
        };
    }

    /** Métodos que envolvem uma conta bancária. */
    public function exigeBanco(): bool
    {
        return $this !== self::Dinheiro;
    }
}
