<?php

namespace App\Models;

use App\Enums\StatusObra;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['unidade_id', 'iniciada_em', 'previsao_termino', 'encerrada_em', 'status', 'verba'])]
class Obra extends Model
{
    use Auditavel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciada_em' => 'date',
            'previsao_termino' => 'date',
            'encerrada_em' => 'date',
            'verba' => 'decimal:2',
            'status' => StatusObra::class,
        ];
    }

    /**
     * Unidade à qual esta obra pertence.
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
