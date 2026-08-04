<?php

namespace App\Models;

use App\Enums\StatusRequisicaoMaterial;
use App\Models\Concerns\Auditavel;
use Database\Factories\RequisicaoMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NÃƒO usa o trait PertenceAUnidade: o Solicitante vÃª as prÃ³prias RIMs (filtro por
 * solicitante_id) e o Almoxarife vÃª as da sua unidade (pivot). O filtro Ã© EXPLÃCITO nos
 * componentes/actions, nÃ£o por GlobalScope. Campos como almoxarife_id e
 * movimentacao_estoque_id sÃ£o preenchidos sÃ³ pelas Actions, nunca por input do usuÃ¡rio.
 */
#[Fillable([
    'unidade_id',
    'solicitante_id',
    'almoxarife_id',
    'saldo_estoque_id',
    'quantidade_solicitada',
    'justificativa',
    'status',
    'motivo_recusa',
    'movimentacao_estoque_id',
    'atendida_em',
    'recusada_em',
])]
class RequisicaoMaterial extends ComprasModel
{
    /** @use HasFactory<RequisicaoMaterialFactory> */
    use Auditavel, HasFactory;

    protected $table = 'requisicoes_material';

    protected function casts(): array
    {
        return [
            'status' => StatusRequisicaoMaterial::class,
            'quantidade_solicitada' => 'decimal:3',
            'atendida_em' => 'datetime',
            'recusada_em' => 'datetime',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function almoxarife(): BelongsTo
    {
        return $this->belongsTo(User::class, 'almoxarife_id');
    }

    public function saldoEstoque(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class);
    }

    public function movimentacao(): BelongsTo
    {
        return $this->belongsTo(MovimentacaoEstoque::class, 'movimentacao_estoque_id');
    }
}
