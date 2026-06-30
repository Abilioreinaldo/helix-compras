<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ItemRequisicaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requisicao_id', 'descricao', 'quantidade', 'unidade_medida', 'valor_unitario_estimado', 'item_catalogo_id', 'avulso', 'rejeitado_em', 'rejeitado_por', 'motivo_rejeicao'])]
class ItemRequisicao extends Model
{
    /** @use HasFactory<ItemRequisicaoFactory> */
    use Auditavel, HasFactory;

    protected $table = 'requisicao_itens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario_estimado' => 'decimal:2',
            'avulso' => 'boolean',
            'rejeitado_em' => 'datetime',
        ];
    }

    /** Item rejeitado por um aprovador (decisão por linha) e fora da compra. */
    public function estaRejeitado(): bool
    {
        return $this->rejeitado_em !== null;
    }

    /** @param  Builder<ItemRequisicao>  $query */
    public function scopeNaoRejeitado(Builder $query): void
    {
        $query->whereNull('rejeitado_em');
    }

    public function rejeitador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejeitado_por');
    }

    /**
     * Requisição pai deste item.
     */
    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    /**
     * Item de catálogo vinculado, quando não avulso.
     */
    public function catalogoItem(): BelongsTo
    {
        return $this->belongsTo(CatalogoItem::class, 'item_catalogo_id');
    }
}
