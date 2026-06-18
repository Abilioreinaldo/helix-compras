<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\LoteEstoqueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'saldo_estoque_id',
    'numero_lote',
    'validade',
    'quantidade',
    'fundido_para_id',
    'fundido_em',
])]
class LoteEstoque extends Model
{
    /** @use HasFactory<LoteEstoqueFactory> */
    use Auditavel, HasFactory;

    protected $table = 'lotes_estoque';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'validade' => 'date',
            'fundido_em' => 'datetime',
        ];
    }

    /** Saldo agregado ao qual este lote pertence. */
    public function saldoEstoque(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class);
    }

    /** Lote destino ao qual este lote foi fundido (se for tombstone). */
    public function fundidoPara(): BelongsTo
    {
        return $this->belongsTo(LoteEstoque::class, 'fundido_para_id');
    }

    /** Lotes tombstone que foram fundidos neste lote. */
    public function fundidosDe(): HasMany
    {
        return $this->hasMany(LoteEstoque::class, 'fundido_para_id');
    }
}
