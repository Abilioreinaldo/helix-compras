<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ItemPedidoCompraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'pedido_compra_id',
    'requisicao_id',
    'item_requisicao_id',
    'cotacao_id',
    'descricao',
    'quantidade',
    'unidade_medida',
    'valor_unitario',
    'valor_total',
    'destino',
    'item_catalogo_id',
    'avulso',
])]
class ItemPedidoCompra extends Model
{
    /** @use HasFactory<ItemPedidoCompraFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'itens_pedido_compra';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'avulso' => 'boolean',
        ];
    }

    public function pedidoCompra(): BelongsTo
    {
        return $this->belongsTo(PedidoCompra::class);
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    public function itemRequisicao(): BelongsTo
    {
        return $this->belongsTo(ItemRequisicao::class, 'item_requisicao_id');
    }

    public function cotacao(): BelongsTo
    {
        return $this->belongsTo(Cotacao::class);
    }

    public function catalogoItem(): BelongsTo
    {
        return $this->belongsTo(CatalogoItem::class, 'item_catalogo_id');
    }
}
