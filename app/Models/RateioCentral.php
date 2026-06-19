<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\RateioCentralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mes',
    'ano',
    'valor_total',
    'criado_por',
])]
class RateioCentral extends Model
{
    /** @use HasFactory<RateioCentralFactory> */
    use Auditavel, HasFactory;

    protected $table = 'rateios_centrais';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'ano' => 'integer',
            'valor_total' => 'decimal:2',
        ];
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** Linhas por unidade (percentual + valor rateado). */
    public function unidades(): HasMany
    {
        return $this->hasMany(RateioUnidade::class);
    }
}
