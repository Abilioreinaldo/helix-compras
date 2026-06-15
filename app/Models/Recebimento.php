<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'pedido_compra_id',
    'almoxarife_id',
    'recebido_em',
    'observacoes',
])]
class Recebimento extends Model
{
    use Auditavel, SoftDeletes;

    protected function casts(): array
    {
        return [
            'recebido_em' => 'datetime',
        ];
    }

    public function pedidoCompra(): BelongsTo
    {
        return $this->belongsTo(PedidoCompra::class);
    }

    public function almoxarife(): BelongsTo
    {
        return $this->belongsTo(User::class, 'almoxarife_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemRecebimento::class);
    }
}
