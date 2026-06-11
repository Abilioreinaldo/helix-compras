<?php

namespace App\Models;

use App\Enums\NivelAlcada;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['faixa_alcada_id', 'ordem', 'nivel_exigido'])]
class EtapaAlcada extends Model
{
    use Auditavel, HasFactory;

    protected $table = 'etapas_alcada';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel_exigido' => NivelAlcada::class,
            'ordem' => 'integer',
        ];
    }

    /**
     * Faixa de alçada à qual esta etapa pertence.
     */
    public function faixa(): BelongsTo
    {
        return $this->belongsTo(FaixaAlcada::class, 'faixa_alcada_id');
    }
}
