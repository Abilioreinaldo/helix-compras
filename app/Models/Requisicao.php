<?php

namespace App\Models;

use App\Enums\StatusRequisicao;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAUnidade;
use Database\Factories\RequisicaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'solicitante_id',
    'unidade_id',
    'centro_custo_id',
    'obra_id',
    'status',
    'urgente',
    'is_emergencial',
    'justificativa',
    'atrasada',
    'faixa_alcada_id',
    'escalada_verba',
    'consumo_verba_no_submit',
    'submetida_em',
    'triagem_iniciada_em',
    'cancelada_em',
    'cancelada_por',
    'motivo_cancelamento',
    'codigo',
    'primeira_cotacao_em',
    'cotacao_concluida_em',
])]
class Requisicao extends Model
{
    /** @use HasFactory<RequisicaoFactory> */
    use Auditavel, HasFactory, PertenceAUnidade, SoftDeletes;

    protected $table = 'requisicoes';

    /**
     * Coluna que referencia a unidade para o UnidadeScope.
     */
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
            'status' => StatusRequisicao::class,
            'urgente' => 'boolean',
            'is_emergencial' => 'boolean',
            'atrasada' => 'boolean',
            'escalada_verba' => 'boolean',
            'consumo_verba_no_submit' => 'decimal:2',
            'submetida_em' => 'datetime',
            'triagem_iniciada_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'primeira_cotacao_em' => 'datetime',
            'cotacao_concluida_em' => 'datetime',
        ];
    }

    /**
     * Usuário solicitante desta requisição.
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /**
     * Unidade à qual esta requisição pertence.
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * Centro de custo vinculado à requisição.
     */
    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }

    /**
     * Obra vinculada à requisição (opcional).
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * Faixa de alçada snapshot no momento da submissão.
     */
    public function faixaAlcada(): BelongsTo
    {
        return $this->belongsTo(FaixaAlcada::class, 'faixa_alcada_id');
    }

    /**
     * Itens desta requisição.
     */
    public function itens(): HasMany
    {
        return $this->hasMany(ItemRequisicao::class);
    }

    /**
     * Logs de transição de status desta requisição.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(RequisicaoLog::class);
    }

    /**
     * Cotações vinculadas a esta requisição.
     */
    public function cotacoes(): HasMany
    {
        return $this->hasMany(Cotacao::class);
    }

    /**
     * Calcula o valor total estimado somando quantidade × valor_unitario_estimado dos itens.
     */
    public function valorTotal(): float
    {
        return (float) $this->itens->sum(
            fn (ItemRequisicao $item) => ($item->quantidade ?? 0) * ($item->valor_unitario_estimado ?? 0)
        );
    }
}
