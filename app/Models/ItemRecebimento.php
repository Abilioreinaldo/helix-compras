<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'recebimento_id',
    'item_pedido_compra_id',
    'quantidade_recebida',
])]
class ItemRecebimento extends Model
{
    use SoftDeletes;

    protected $table = 'itens_recebimento';

    protected function casts(): array
    {
        return [
            'quantidade_recebida' => 'decimal:3',
        ];
    }

    public function recebimento(): BelongsTo
    {
        return $this->belongsTo(Recebimento::class);
    }

    public function itemPedidoCompra(): BelongsTo
    {
        return $this->belongsTo(ItemPedidoCompra::class, 'item_pedido_compra_id');
    }
}
