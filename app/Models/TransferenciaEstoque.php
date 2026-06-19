<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\TransferenciaEstoqueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'saldo_origem_id',
    'saldo_destino_id',
    'unidade_destino_id',
    'quantidade',
    'custo_unitario',
    'valor_total',
    'motivo',
    'executado_por',
])]
class TransferenciaEstoque extends Model
{
    /** @use HasFactory<TransferenciaEstoqueFactory> */
    use Auditavel, HasFactory;

    protected $table = 'transferencias_estoque';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
            'valor_total' => 'decimal:2',
        ];
    }

    public function saldoOrigem(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class, 'saldo_origem_id');
    }

    public function saldoDestino(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class, 'saldo_destino_id');
    }

    public function unidadeDestino(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_destino_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executado_por');
    }

    /** As duas movimentações do ledger (saída na origem + entrada no destino). */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'transferencia_estoque_id');
    }
}
