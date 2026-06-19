<?php

namespace App\Models;

use App\Enums\TipoMovimentacao;
use Database\Factories\RateioUnidadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rateio_central_id',
    'unidade_id',
    'percentual_consumo',
    'valor_rateado',
])]
class RateioUnidade extends Model
{
    /** @use HasFactory<RateioUnidadeFactory> */
    use HasFactory;

    protected $table = 'rateio_unidades';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentual_consumo' => 'decimal:4',
            'valor_rateado' => 'decimal:2',
        ];
    }

    public function rateioCentral(): BelongsTo
    {
        return $this->belongsTo(RateioCentral::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /** Movimentações do ledger ligadas a esta linha de rateio (rateio + eventual desconto). */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'rateio_unidade_id');
    }

    /** Indica se esta linha já foi revertida por um DescontoRateio. */
    public function foiRevertido(): bool
    {
        return $this->movimentacoes()
            ->where('tipo', TipoMovimentacao::DescontoRateio->value)
            ->exists();
    }
}
