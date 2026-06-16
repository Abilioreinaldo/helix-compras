<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\CatalogoItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'codigo',
    'descricao',
    'unidade_medida',
    'categoria',
    'ativo',
])]
class CatalogoItem extends Model
{
    /** @use HasFactory<CatalogoItemFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'catalogo_itens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CatalogoItem $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }
}
