<?php

namespace App\Enums;

/** Papéis disponíveis no sistema de compras. */
enum Perfil: string
{
    case Admin = 'admin';
    case CompradoraSenior = 'compradora_senior';
    case Aprovador = 'aprovador';
    case Solicitante = 'solicitante';
    case Almoxarife = 'almoxarife';
}
