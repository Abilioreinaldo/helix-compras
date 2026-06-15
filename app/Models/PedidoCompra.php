<?php

namespace App\Models;

use App\Enums\ModalidadeEntrega;
use App\Enums\StatusPedidoCompra;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAUnidade;
use Database\Factories\PedidoCompraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'numero',
    'ano',
    'sequencia',
    'status',
    'fornecedor_id',
    'unidade_id',
    'condicoes_pagamento',
    'observacoes',
    'prazo_entrega',
    'modalidade_entrega',
    'criado_por',
    'emitido_em',
    'emitido_por',
    'cancelado_em',
    'cancelado_por',
    'motivo_cancelamento',
])]
class PedidoCompra extends Model
{
    /** @use HasFactory<PedidoCompraFactory> */
    use Auditavel, HasFactory, PertenceAUnidade, SoftDeletes;

    protected $table = 'pedidos_compra';

    public static function colunaUnidade(): string
    {
        return 'unidade_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPedidoCompra::class,
            'ano' => 'integer',
            'sequencia' => 'integer',
            'prazo_entrega' => 'date',
            'modalidade_entrega' => ModalidadeEntrega::class,
            'emitido_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function emissor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function cancelador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemPedidoCompra::class)->orderBy('requisicao_id')->orderBy('id');
    }

    /** Valor total do pedido (soma dos itens). */
    public function valorTotal(): float
    {
        return (float) $this->itens()->sum('valor_total');
    }

    /** Requisições distintas vinculadas a este pedido via itens. */
    public function requisicoesVinculadas(): Collection
    {
        $ids = $this->itens()->distinct()->pluck('requisicao_id');

        return Requisicao::withoutGlobalScopes()->whereIn('id', $ids)->get();
    }

    /**
     * Itens agrupados por destino (para o PDF).
     *
     * @return array<string, Collection>
     */
    public function itensPorDestino(): array
    {
        return $this->itens->groupBy(fn (ItemPedidoCompra $item) => $item->destino ?? 'Não definido')->toArray();
    }
}
