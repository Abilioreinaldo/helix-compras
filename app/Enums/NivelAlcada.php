<?php

namespace App\Enums;

/** Nível de alçada do aprovador por unidade. */
enum NivelAlcada: string
{
    case Gestor = 'gestor';
    case Diretor = 'diretor';
    case Ceo = 'ceo';
}
