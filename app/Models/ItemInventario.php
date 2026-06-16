<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ItemInventarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sessao_inventario_id',
    'saldo_estoque_id',
    'quantidade_sistema',
    'quantidade_contada',
    'movimentacao_estoque_id',
])]
class ItemInventario extends Model
{
    /** @use HasFactory<ItemInventarioFactory> */
    use Auditavel, HasFactory;

    protected $table = 'itens_inventario';

    protected function casts(): array
    {
        return [
            'quantidade_sistema' => 'decimal:3',
            'quantidade_contada' => 'decimal:3',
        ];
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(SessaoInventario::class, 'sessao_inventario_id');
    }

    public function saldoEstoque(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class);
    }

    public function movimentacao(): BelongsTo
    {
        return $this->belongsTo(MovimentacaoEstoque::class, 'movimentacao_estoque_id');
    }

    /**
     * Divergência entre quantidade contada e sistema (positiva = sobra, negativa = falta).
     */
    public function getDivergenciaAttribute(): ?float
    {
        if ($this->quantidade_contada === null) {
            return null;
        }

        return (float) $this->quantidade_contada - (float) $this->quantidade_sistema;
    }
}
