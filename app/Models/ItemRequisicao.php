<?php

namespace App\Models;

use Database\Factories\ItemRequisicaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requisicao_id', 'descricao', 'quantidade', 'unidade_medida', 'valor_unitario_estimado'])]
class ItemRequisicao extends Model
{
    /** @use HasFactory<ItemRequisicaoFactory> */
    use HasFactory;

    protected $table = 'requisicao_itens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario_estimado' => 'decimal:2',
        ];
    }

    /**
     * Requisição pai deste item.
     */
    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }
}
