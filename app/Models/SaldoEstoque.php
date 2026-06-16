<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unidade_id',
    'deposito',
    'descricao_item',
    'descricao_normalizada',
    'unidade_medida',
    'quantidade',
    'custo_medio_ponderado',
    'valor_total',
    'item_catalogo_id',
])]
class SaldoEstoque extends Model
{
    use Auditavel;

    protected $table = 'saldos_estoque';

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'custo_medio_ponderado' => 'decimal:4',
            'valor_total' => 'decimal:2',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function catalogoItem(): BelongsTo
    {
        return $this->belongsTo(CatalogoItem::class, 'item_catalogo_id');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class)->orderByDesc('created_at');
    }

    /** Normaliza a descrição para busca/unicidade: trim + lowercase + colapsa espaços múltiplos. */
    public static function normalizarDescricao(string $descricao): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($descricao)));
    }
}
