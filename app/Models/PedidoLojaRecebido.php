<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de compra recebido da loja (Store) pelo transporte inter-app (ADR-015).
 *
 * Zona de pouso (inbox): guarda o snapshot do contrato `store.purchase_request.created`
 * idempotente por `tenant_id + request_code`. Não é uma Requisicao — o comprador
 * promove manualmente (mapeando loja→unidade, CNPJ→fornecedor, itens→catálogo),
 * porque solicitante/unidade/fornecedor da loja não casam com as entidades do
 * Compras. Herda o escopo de tenant do ComprasModel (carimbo automático no
 * contexto do evento durante o ProcessDomainEvent).
 */
#[Fillable([
    'request_code',
    'store_code',
    'supplier_cnpj',
    'supplier_name',
    'line_count',
    'total_estimated_cents',
    'payload',
    'status',
    'requisicao_id',
    'recebido_em',
])]
class PedidoLojaRecebido extends ComprasModel
{
    use Auditavel;

    protected $table = 'pedidos_loja_recebidos';

    public const STATUS_RECEBIDO = 'recebido';

    public const STATUS_PROMOVIDO = 'promovido';

    public const STATUS_DESCARTADO = 'descartado';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'line_count' => 'integer',
            'total_estimated_cents' => 'integer',
            'recebido_em' => 'datetime',
        ];
    }

    /** A Requisicao gerada ao promover este pedido (null enquanto não promovido). */
    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }
}
