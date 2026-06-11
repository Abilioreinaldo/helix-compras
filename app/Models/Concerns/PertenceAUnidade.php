<?php

namespace App\Models\Concerns;

use App\Models\Scopes\UnidadeScope;

/**
 * Registra automaticamente o UnidadeScope no model que usa esta trait.
 * Models que referenciam unidades por FK devem sobrescrever colunaUnidade().
 */
trait PertenceAUnidade
{
    protected static function bootPertenceAUnidade(): void
    {
        static::addGlobalScope(new UnidadeScope);
    }

    /**
     * Coluna usada para filtrar pelo id da unidade.
     * Unidade usa 'id'; models filhos (Obra, etc.) usam 'unidade_id'.
     */
    public static function colunaUnidade(): string
    {
        return 'id';
    }
}
