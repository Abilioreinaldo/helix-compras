<?php

namespace App\Enums;

/** Status de andamento de uma obra. */
enum StatusObra: string
{
    case Ativa = 'ativa';
    case Encerrada = 'encerrada';
}
