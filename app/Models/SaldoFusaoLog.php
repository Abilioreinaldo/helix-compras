<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log imutável (append-only) de fusões de saldos de estoque.
 * Preserva snapshot completo do saldo origem antes da fusão.
 * Sem updated_at — padrão de log de auditoria do projeto.
 */
#[Fillable([
    'saldo_destino_id',
    'saldo_origem_id',
    'quantidade_origem',
    'cmp_origem',
    'valor_total_origem',
    'item_catalogo_id_origem',
    'descricao_normalizada_origem',
    'deposito_origem',
    'unidade_id_origem',
    'executado_por',
])]
class SaldoFusaoLog extends Model
{
    protected $table = 'saldo_fusao_log';

    /** Log imutável — sem updated_at. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade_origem' => 'decimal:3',
            'cmp_origem' => 'decimal:4',
            'valor_total_origem' => 'decimal:2',
        ];
    }

    public function saldoDestino(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class, 'saldo_destino_id');
    }

    public function saldoOrigem(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class, 'saldo_origem_id');
    }

    public function executador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executado_por');
    }
}
