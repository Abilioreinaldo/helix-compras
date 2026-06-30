<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\CatalogoItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'codigo',
    'descricao',
    'unidade_medida',
    'categoria',
    'ativo',
    'controla_lote',
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
            'controla_lote' => 'boolean',
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

    /**
     * Estoques mínimos definidos para este item de catálogo.
     */
    public function estoqueMinimos(): HasMany
    {
        return $this->hasMany(EstoqueMinimo::class, 'item_catalogo_id');
    }

    /**
     * Preços homologados deste item (qualquer fornecedor/validade).
     */
    public function precosHomologados(): HasMany
    {
        return $this->hasMany(PrecoHomologado::class, 'item_catalogo_id');
    }

    /**
     * Resolve o preço homologado preferencial e válido para a data informada
     * (hoje por padrão): ativo e dentro da janela de validade. Desempate por
     * `preferencial`, depois pelo menor preço. Retorna null se não houver.
     *
     * Filtro de data por bind (string), sem função de dialeto — portável SQLite↔MySQL.
     */
    public function precoHomologadoValido(?Carbon $data = null): ?PrecoHomologado
    {
        $dia = ($data ?? now())->toDateString();

        return $this->precosHomologados()
            ->where('ativo', true)
            ->where('validade_inicio', '<=', $dia)
            ->where('validade_fim', '>=', $dia)
            ->orderByDesc('preferencial')
            ->orderBy('preco')
            ->first();
    }
}
