<?php

namespace App\Models;

use App\Enums\StatusUnidade;
use App\Enums\TipoUnidade;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAUnidade;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'nome', 'tipo', 'cnpj', 'endereco', 'gestor_id', 'status'])]
class Unidade extends ComprasModel
{
    use Auditavel, HasFactory, PertenceAUnidade, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoUnidade::class,
            'status' => StatusUnidade::class,
        ];
    }

    /**
     * Gestor responsÃ¡vel pela unidade.
     */
    public function gestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestor_id');
    }

    /**
     * Dados complementares da obra (somente para unidades do tipo obra).
     */
    public function obra(): HasOne
    {
        return $this->hasOne(Obra::class);
    }

    /**
     * UsuÃ¡rios vinculados a esta unidade.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unidade_user')
            ->withPivot(['perfil', 'nivel_alcada'])
            ->withTimestamps();
    }
}
