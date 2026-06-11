<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\CotacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'requisicao_id',
    'fornecedor_id',
    'valor',
    'prazo_entrega_dias',
    'arquivo_path',
    'arquivo_nome_original',
    'observacoes',
    'vencedora',
    'criada_por',
    'vencedora_definida_em',
    'vencedora_definida_por',
    'cancelada_em',
    'motivo_cancelamento',
])]
class Cotacao extends Model
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
            'valor' => 'decimal:2',
            'vencedora' => 'boolean',
            'prazo_entrega_dias' => 'integer',
            'vencedora_definida_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
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
