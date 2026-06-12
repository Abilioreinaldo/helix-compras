<?php

namespace App\Models;

use App\Enums\NivelAlcada;
use App\Enums\StatusAprovacao;
use App\Models\Concerns\Auditavel;
use Database\Factories\AprovacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'requisicao_id',
    'etapa_alcada_id',
    'ciclo',
    'ordem',
    'nivel_exigido',
    'obrigatoria_emergencial',
    'status',
    'aprovador_id',
    'justificativa',
    'decidida_em',
])]
class Aprovacao extends Model
{
    /** @use HasFactory<AprovacaoFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'aprovacoes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusAprovacao::class,
            'nivel_exigido' => NivelAlcada::class,
            'ciclo' => 'integer',
            'ordem' => 'integer',
            'obrigatoria_emergencial' => 'boolean',
            'decidida_em' => 'datetime',
        ];
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    public function etapaAlcada(): BelongsTo
    {
        return $this->belongsTo(EtapaAlcada::class, 'etapa_alcada_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovador_id');
    }
}
