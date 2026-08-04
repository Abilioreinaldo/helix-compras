<?php

namespace App\Models;

use App\Enums\OrigemCotacao;
use App\Models\Concerns\Auditavel;
use Database\Factories\CotacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'requisicao_id',
    'origem',
    'fornecedor_id',
    'valor',
    'prazo_entrega_dias',
    'validade_proposta',
    'arquivo_path',
    'arquivo_nome_original',
    'observacoes',
    'vencedora',
    'criada_por',
    'vencedora_definida_em',
    'vencedora_definida_por',
    'cancelada_em',
    'motivo_cancelamento',
    // Captura IMAP (advisory) â€” sugestÃ£o extraÃ­da do e-mail do fornecedor.
    'valor_respondido',
    'prazo_respondido',
    'observacoes_fornecedor',
    'resposta_recebida_em',
    'email_externo_id',
])]
class Cotacao extends ComprasModel
{
    /** @use HasFactory<CotacaoFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'cotacoes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origem' => OrigemCotacao::class,
            'valor' => 'decimal:2',
            'vencedora' => 'boolean',
            'prazo_entrega_dias' => 'integer',
            'validade_proposta' => 'date',
            'vencedora_definida_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'valor_respondido' => 'decimal:2',
            'prazo_respondido' => 'integer',
            'resposta_recebida_em' => 'datetime',
        ];
    }

    /** HÃ¡ uma sugestÃ£o de valor capturada do e-mail do fornecedor (ainda nÃ£o confirmada). */
    public function temRespostaSugerida(): bool
    {
        return $this->valor_respondido !== null;
    }

    /** A compradora jÃ¡ confirmou um valor oficial para esta cotaÃ§Ã£o. */
    public function valorConfirmado(): bool
    {
        return $this->valor !== null;
    }

    /** Dias decorridos desde a criaÃ§Ã£o/solicitaÃ§Ã£o da cotaÃ§Ã£o. */
    public function diasAguardando(): int
    {
        return (int) $this->created_at->diffInDays(now());
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    /** PreÃ§os por item desta cotaÃ§Ã£o (matriz). O total (coluna `valor`) Ã© a soma das linhas. */
    public function itensCotacao(): HasMany
    {
        return $this->hasMany(ItemCotacao::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    public function definidorVencedora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vencedora_definida_por');
    }
}
