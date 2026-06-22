<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ReconciliacaoBancariaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro (log) de uma reconciliação bancária a partir de um extrato CSV.
 */
#[Fillable([
    'banco_id',
    'data_arquivo',
    'total_linhas',
    'total_processado',
    'total_conciliado',
    'arquivo_hash',
    'criado_por',
])]
class ReconciliacaoBancaria extends Model
{
    /** @use HasFactory<ReconciliacaoBancariaFactory> */
    use Auditavel, HasFactory;

    protected $table = 'reconciliacoes_bancarias';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_arquivo' => 'date',
            'total_processado' => 'decimal:2',
            'total_conciliado' => 'decimal:2',
            'total_linhas' => 'integer',
        ];
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemReconciliacao::class);
    }

    public function totalConciliado(): float
    {
        return (float) $this->itens()->whereNotNull('pagamento_id')->sum('valor');
    }
}
