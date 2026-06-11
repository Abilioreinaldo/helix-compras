<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nome', 'valor_minimo', 'valor_maximo', 'is_emergencial', 'ativo'])]
class FaixaAlcada extends Model
{
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'faixas_alcada';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_minimo' => 'decimal:2',
            'valor_maximo' => 'decimal:2',
            'is_emergencial' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Etapas de aprovação desta faixa, ordenadas pela sequência.
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(EtapaAlcada::class)->orderBy('ordem');
    }

    /**
     * Scope para retornar apenas as faixas ativas.
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
