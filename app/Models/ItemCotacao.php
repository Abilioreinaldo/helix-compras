<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ItemCotacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PreÃ§o unitÃ¡rio cotado por um fornecedor (Cotacao) para um item da requisiÃ§Ã£o.
 * A linha vale valor_unitario Ã— quantidade do item; o total da cotaÃ§Ã£o Ã© a soma das linhas.
 *
 * Em produÃ§Ã£o, cotacao_id Ã© sempre definido via relaÃ§Ã£o ($cotacao->itensCotacao()->create);
 * nenhum caminho passa cotacao_id de input do usuÃ¡rio para create direto.
 */
#[Fillable([
    'cotacao_id',
    'item_requisicao_id',
    'valor_unitario',
])]
class ItemCotacao extends ComprasModel
{
    /** @use HasFactory<ItemCotacaoFactory> */
    use Auditavel, HasFactory;

    protected $table = 'itens_cotacao';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
        ];
    }

    public function cotacao(): BelongsTo
    {
        return $this->belongsTo(Cotacao::class);
    }

    public function itemRequisicao(): BelongsTo
    {
        return $this->belongsTo(ItemRequisicao::class, 'item_requisicao_id');
    }

    /** Valor da linha: unitÃ¡rio Ã— quantidade do item da requisiÃ§Ã£o. */
    public function valorLinha(): float
    {
        return round((float) $this->valor_unitario * (float) ($this->itemRequisicao->quantidade ?? 0), 2);
    }
}
