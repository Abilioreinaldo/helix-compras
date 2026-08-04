<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ItemReconciliacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha de um extrato bancÃ¡rio processado. status: pendente_match | conciliado | orfao.
 */
#[Fillable([
    'reconciliacao_bancaria_id',
    'numero_documento',
    'valor',
    'data_transacao',
    'descricao',
    'pagamento_id',
    'status',
])]
class ItemReconciliacao extends ComprasModel
{
    /** @use HasFactory<ItemReconciliacaoFactory> */
    use Auditavel, HasFactory;

    protected $table = 'itens_reconciliacao';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_transacao' => 'date',
        ];
    }

    public function reconciliacao(): BelongsTo
    {
        return $this->belongsTo(ReconciliacaoBancaria::class, 'reconciliacao_bancaria_id');
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }
}
