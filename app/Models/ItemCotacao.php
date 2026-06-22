<?php

namespace App\Models;

use Database\Factories\ItemCotacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preço unitário cotado por um fornecedor (Cotacao) para um item da requisição.
 * A linha vale valor_unitario × quantidade do item; o total da cotação é a soma das linhas.
 */
#[Fillable([
    'cotacao_id',
    'item_requisicao_id',
    'valor_unitario',
])]
class ItemCotacao extends Model
{
    /** @use HasFactory<ItemCotacaoFactory> */
    use HasFactory;

    protected $table = 'itens_cotacao';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
        ];
    }

    public function cotacao(): BelongsTo
    {
        return $this->belongsTo(Cotacao::class);
    }

    public function itemRequisicao(): BelongsTo
    {
        return $this->belongsTo(ItemRequisicao::class, 'item_requisicao_id');
    }

    /** Valor da linha: unitário × quantidade do item da requisição. */
    public function valorLinha(): float
    {
        return round((float) $this->valor_unitario * (float) ($this->itemRequisicao->quantidade ?? 0), 2);
    }
}
