<?php

namespace App\Models;

use App\Enums\MetodoPagamento;
use App\Enums\StatusPagamento;
use App\Models\Concerns\Auditavel;
use Database\Factories\PagamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Conta a pagar — gerada ao emitir um Pedido de Compra. Área de dinheiro (Auditavel).
 */
#[Fillable([
    'pedido_compra_id',
    'fornecedor_id',
    'banco_id',
    'numero_nf',
    'data_emissao',
    'data_vencimento',
    'valor_total',
    'valor_pago',
    'valor_juros',
    'valor_multa',
    'valor_desconto',
    'status',
    'metodo_pagamento',
    'data_pagamento',
    'referencia_banco',
    'numero_cheque',
    'agendado_para',
    'observacoes',
    'criado_por',
    'atualizado_por',
])]
class Pagamento extends Model
{
    /** @use HasFactory<PagamentoFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'pagamentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_vencimento' => 'date',
            'data_pagamento' => 'date',
            'agendado_para' => 'date',
            'valor_total' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'valor_juros' => 'decimal:2',
            'valor_multa' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'status' => StatusPagamento::class,
            'metodo_pagamento' => MetodoPagamento::class,
        ];
    }

    public function pedidoCompra(): BelongsTo
    {
        return $this->belongsTo(PedidoCompra::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function itensReconciliacao(): HasMany
    {
        return $this->hasMany(ItemReconciliacao::class);
    }

    /** Total devido: total - desconto + juros + multa. */
    public function calcularTotal(): float
    {
        return round(
            (float) $this->valor_total
            - (float) $this->valor_desconto
            + (float) $this->valor_juros
            + (float) $this->valor_multa,
            2
        );
    }

    public function temDesconto(): bool
    {
        return (float) $this->valor_desconto > 0;
    }

    /** Dias até o vencimento (negativo se já venceu). */
    public function diasAteVencimento(): int
    {
        return (int) Carbon::today()->diffInDays($this->data_vencimento, false);
    }

    /** Está vencido: em aberto e com vencimento no passado. */
    public function ehVencido(): bool
    {
        if (in_array($this->status, [StatusPagamento::Pago, StatusPagamento::Cancelado], true)) {
            return false;
        }

        return $this->data_vencimento !== null && $this->data_vencimento->isPast();
    }
}
