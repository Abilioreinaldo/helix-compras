<?php

namespace App\Enums;

/** Tipos de unidade da rede Comendador. */
enum TipoUnidade: string
{
    case Posto = 'posto';
    case Obra = 'obra';
    case Cervejaria = 'cervejaria';
    case Central = 'central';
    case Imobiliaria = 'imobiliaria';
}
