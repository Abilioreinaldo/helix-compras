<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['auditavel_type', 'auditavel_id', 'campo', 'valor_anterior', 'valor_novo', 'evento', 'user_id', 'created_at'])]
class Auditoria extends Model
{
    /**
     * Log imutável: não possui updated_at.
     */
    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Retorna o model auditado (polimórfico).
     */
    public function auditavel(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuário que originou o evento (nullable para ações de sistema/job).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
