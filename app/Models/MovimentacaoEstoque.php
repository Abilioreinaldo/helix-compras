<?php

namespace App\Models;

use App\Enums\TipoMovimentacao;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'saldo_estoque_id',
    'item_recebimento_id',
    'item_pedido_compra_id',
    'requisicao_material_id',
    'tipo',
    'quantidade',
    'custo_unitario',
    'valor_total',
    'motivo',
    'registrado_por',
])]
class MovimentacaoEstoque extends Model
{
    use Auditavel;

    protected $table = 'movimentacoes_estoque';

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacao::class,
            'quantidade' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
            'valor_total' => 'decimal:2',
        ];
    }

    public function saldoEstoque(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class);
    }

    public function itemRecebimento(): BelongsTo
    {
        return $this->belongsTo(ItemRecebimento::class);
    }

    public function itemPedidoCompra(): BelongsTo
    {
        return $this->belongsTo(ItemPedidoCompra::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function requisicaoMaterial(): BelongsTo
    {
        return $this->belongsTo(RequisicaoMaterial::class);
    }
}
