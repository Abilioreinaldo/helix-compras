<?php

namespace App\Models\Concerns;

use App\Models\Scopes\UnidadeScope;

/**
 * Registra automaticamente o UnidadeScope no model que usa esta trait.
 */
trait PertenceAUnidade
{
    protected static function bootPertenceAUnidade(): void
    {
        static::addGlobalScope(new UnidadeScope);
    }
}
