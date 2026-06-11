<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAUnidade;
use Database\Factories\CentroCustoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['unidade_id', 'codigo', 'nome', 'gestor_id', 'ativo'])]
class CentroCusto extends Model
{
    /** @use HasFactory<CentroCustoFactory> */
    use Auditavel, HasFactory, PertenceAUnidade, SoftDeletes;

    protected $table = 'centros_custo';

    /**
     * Coluna que referencia a unidade para o UnidadeScope.
     */
    public static function colunaUnidade(): string
    {
        return 'unidade_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /**
     * Unidade à qual este centro de custo pertence.
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * Gestor responsável pelo centro de custo.
     */
    public function gestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestor_id');
    }
}
