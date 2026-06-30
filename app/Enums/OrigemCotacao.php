<?php

namespace App\Enums;

/** Origem de uma cotação registrada no sistema. */
enum OrigemCotacao: string
{
    /** Registrada manualmente pela Compradora. */
    case Manual = 'manual';

    /** Sugerida a partir de resposta de e-mail do fornecedor. */
    case Email = 'email';

    /** Gerada automaticamente a partir de um preço homologado (via expressa). */
    case Homologado = 'homologado';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Email => 'E-mail',
            self::Homologado => 'Homologado',
        };
    }
}
