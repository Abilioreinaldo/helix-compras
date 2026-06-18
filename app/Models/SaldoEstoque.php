<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\SaldoEstoqueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    'fundido_para_id',
    'fundido_em',
])]
class SaldoEstoque extends Model
{
    /** @use HasFactory<SaldoEstoqueFactory> */
    use Auditavel, HasFactory;

    protected $table = 'saldos_estoque';

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'custo_medio_ponderado' => 'decimal:4',
            'valor_total' => 'decimal:2',
            'fundido_em' => 'datetime',
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

    /** Saldo destino ao qual este saldo foi fundido (se for tombstone). */
    public function fundidoPara(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class, 'fundido_para_id');
    }

    /** Saldos tombstone que foram fundidos neste saldo. */
    public function fundidosDe(): HasMany
    {
        return $this->hasMany(SaldoEstoque::class, 'fundido_para_id');
    }

    /** Logs de fusão onde este saldo é o destino. */
    public function fusaoLogs(): HasMany
    {
        return $this->hasMany(SaldoFusaoLog::class, 'saldo_destino_id');
    }

    /** Todos os lotes vinculados a este saldo. */
    public function lotes(): HasMany
    {
        return $this->hasMany(LoteEstoque::class);
    }

    /** Lotes vivos (não tombstones) vinculados a este saldo. */
    public function lotesVivos(): HasMany
    {
        return $this->hasMany(LoteEstoque::class)->whereNull('fundido_para_id');
    }

    /** Normaliza a descrição para busca/unicidade: trim + lowercase + colapsa espaços múltiplos. */
    public static function normalizarDescricao(string $descricao): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($descricao)));
    }
}
