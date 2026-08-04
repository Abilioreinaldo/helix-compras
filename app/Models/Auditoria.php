<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['auditavel_type', 'auditavel_id', 'campo', 'valor_anterior', 'valor_novo', 'evento', 'user_id', 'created_at'])]
class Auditoria extends ComprasModel
{
    /**
     * Log imutÃ¡vel: nÃ£o possui updated_at.
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
     * Retorna o model auditado (polimÃ³rfico).
     */
    public function auditavel(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * UsuÃ¡rio que originou o evento (nullable para aÃ§Ãµes de sistema/job).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
