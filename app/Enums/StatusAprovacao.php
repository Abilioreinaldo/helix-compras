<?php

namespace App\Enums;

enum StatusAprovacao: string
{
    case Pendente = 'pendente';
    case Aprovada = 'aprovada';
    case Reprovada = 'reprovada';
    case Pulada = 'pulada';

    public function ehTerminal(): bool
    {
        return $this !== self::Pendente;
    }
}
