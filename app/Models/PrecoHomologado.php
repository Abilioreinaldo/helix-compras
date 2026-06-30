<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\PrecoHomologadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Preço de um item de catálogo homologado junto a um fornecedor, com validade.
 * Um preço homologado válido dispensa a cotação ad-hoc na via expressa.
 */
#[Fillable([
    'uuid',
    'item_catalogo_id',
    'fornecedor_id',
    'preco',
    'preferencial',
    'validade_inicio',
    'validade_fim',
    'ativo',
    'observacao',
])]
class PrecoHomologado extends Model
{
    /** @use HasFactory<PrecoHomologadoFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'precos_homologados';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'preferencial' => 'boolean',
            'validade_inicio' => 'date',
            'validade_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PrecoHomologado $preco) {
            if (empty($preco->uuid)) {
                $preco->uuid = (string) Str::uuid();
            }
        });
    }

    public function catalogoItem(): BelongsTo
    {
        return $this->belongsTo(CatalogoItem::class, 'item_catalogo_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
