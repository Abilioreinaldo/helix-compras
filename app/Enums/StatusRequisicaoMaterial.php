<?php

namespace App\Enums;

/** Status possíveis de uma Requisição Interna de Material (RIM). */
enum StatusRequisicaoMaterial: string
{
    case Aberta = 'aberta';
    case Atendida = 'atendida';
    case Recusada = 'recusada';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Atendida => 'Atendida',
            self::Recusada => 'Recusada',
        };
    }

    /** Verifica se este status encerra o ciclo de vida da RIM. */
    public function ehTerminal(): bool
    {
        return in_array($this, [self::Atendida, self::Recusada]);
    }
}
