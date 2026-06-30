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
use Illuminate\Support\Carbon;

#[Fillable([
    'solicitante_id',
    'unidade_id',
    'centro_custo_id',
    'obra_id',
    'status',
    'urgente',
    'is_emergencial',
    'expressa',
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
    'ciclo_aprovacao',
    'aprovacao_iniciada_em',
    'aprovada_em',
    'reprovada_em',
    'reprovada_por',
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
            'expressa' => 'boolean',
            'atrasada' => 'boolean',
            'escalada_verba' => 'boolean',
            'consumo_verba_no_submit' => 'decimal:2',
            'submetida_em' => 'datetime',
            'triagem_iniciada_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'primeira_cotacao_em' => 'datetime',
            'cotacao_concluida_em' => 'datetime',
            'ciclo_aprovacao' => 'integer',
            'aprovacao_iniciada_em' => 'datetime',
            'aprovada_em' => 'datetime',
            'reprovada_em' => 'datetime',
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
     * Itens ativos (não rejeitados na decisão por linha da aprovação).
     */
    public function itensAtivos(): HasMany
    {
        return $this->hasMany(ItemRequisicao::class)->whereNull('rejeitado_em');
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
     * Etapas de aprovação instanciadas para esta requisição.
     */
    public function aprovacoes(): HasMany
    {
        return $this->hasMany(Aprovacao::class)->orderBy('ciclo')->orderBy('ordem');
    }

    /**
     * Etapa de aprovação atualmente pendente (ciclo atual, menor ordem pendente).
     */
    public function etapaAprovacaoAtual(): ?Aprovacao
    {
        return $this->aprovacoes()
            ->where('ciclo', $this->ciclo_aprovacao ?? 1)
            ->where('status', 'pendente')
            ->orderBy('ordem')
            ->first();
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

    /**
     * Valor estimado apenas dos itens aprovados (exclui rejeitados na decisão
     * por linha). Para exibição — a alçada permanece roteada pelo valor total.
     */
    public function valorAprovado(): float
    {
        return (float) $this->itens->whereNull('rejeitado_em')->sum(
            fn (ItemRequisicao $item) => ($item->quantidade ?? 0) * ($item->valor_unitario_estimado ?? 0)
        );
    }

    /**
     * Avalia se a requisição é elegível à via expressa: todos os itens são de
     * catálogo com preço homologado válido na data e todos resolvem ao MESMO
     * fornecedor (invariante de "uma cotação vencedora por requisição").
     *
     * Retorna o fornecedor escolhido e o mapa item_id => PrecoHomologado, ou
     * null se inelegível (item avulso, sem homologação válida ou fornecedores
     * diferentes — neste caso a requisição segue o fluxo normal de cotação).
     *
     * @return array{fornecedor_id: int, precos: array<int, PrecoHomologado>}|null
     */
    public function avaliarViaExpressa(?Carbon $data = null): ?array
    {
        $itens = $this->itens()->with('catalogoItem')->get();

        if ($itens->isEmpty()) {
            return null;
        }

        $precos = [];
        $fornecedorId = null;

        foreach ($itens as $item) {
            if (! $item->item_catalogo_id) {
                return null;
            }

            $homologado = $item->catalogoItem?->precoHomologadoValido($data);

            if (! $homologado) {
                return null;
            }

            if ($fornecedorId === null) {
                $fornecedorId = $homologado->fornecedor_id;
            } elseif ($fornecedorId !== $homologado->fornecedor_id) {
                return null;
            }

            $precos[$item->id] = $homologado;
        }

        return ['fornecedor_id' => $fornecedorId, 'precos' => $precos];
    }
}
