<?php

namespace App\Models;

use App\Enums\StatusInventario;
use App\Models\Concerns\Auditavel;
use Database\Factories\SessaoInventarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NÃO usa o trait PertenceAUnidade (diferente de Requisicao/PedidoCompra): o acesso é por
 * pivot de Almoxarife (suas unidades) ou global (Admin). O filtro de unidade é aplicado
 * EXPLICITAMENTE nos componentes/actions, não por GlobalScope.
 */
#[Fillable([
    'unidade_id',
    'deposito',
    'aberta_por',
    'concluida_por',
    'status',
    'justificativa',
    'concluida_em',
])]
class SessaoInventario extends Model
{
    /** @use HasFactory<SessaoInventarioFactory> */
    use Auditavel, HasFactory;

    protected $table = 'sessoes_inventario';

    protected function casts(): array
    {
        return [
            'status' => StatusInventario::class,
            'concluida_em' => 'datetime',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function abertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberta_por');
    }

    public function concluidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluida_por');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemInventario::class);
    }
}
