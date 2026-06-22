<?php

namespace App\Models;

use Database\Factories\BancoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'codigo_banco', 'ativo'])]
class Banco extends Model
{
    /** @use HasFactory<BancoFactory> */
    use HasFactory;

    protected $table = 'bancos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function reconciliacoes(): HasMany
    {
        return $this->hasMany(ReconciliacaoBancaria::class);
    }

    /**
     * @param  Builder<Banco>  $query
     */
    public function scopeAtivo(Builder $query): void
    {
        $query->where('ativo', true);
    }
}
