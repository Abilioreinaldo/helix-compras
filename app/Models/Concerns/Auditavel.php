<?php

namespace App\Models\Concerns;

use App\Observers\AuditoriaObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra eventos de auditoria usando listeners diretos para evitar
 * o conflito de boot circular que ocorre com static::observe() em traits.
 */
trait Auditavel
{
    protected static function bootAuditavel(): void
    {
        $observer = new AuditoriaObserver;

        static::created(fn (Model $model) => $observer->created($model));
        static::updated(fn (Model $model) => $observer->updated($model));
        static::deleted(fn (Model $model) => $observer->deleted($model));
    }
}
