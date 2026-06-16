<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\ItemRequisicaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requisicao_id', 'descricao', 'quantidade', 'unidade_medida', 'valor_unitario_estimado', 'item_catalogo_id', 'avulso'])]
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
        ];
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
