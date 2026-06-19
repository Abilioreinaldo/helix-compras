<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusRequisicaoMaterial;
use App\Models\RequisicaoMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AtenderRequisicaoMaterialAction
{
    public function __construct(private readonly SaidaEstoqueAction $saidaEstoqueAction) {}

    /**
     * Atende uma Requisição Interna de Material, baixando o saldo do estoque.
     *
     * @throws ValidationException
     */
    public function execute(RequisicaoMaterial $rim, User $almoxarife): RequisicaoMaterial
    {
        if ($rim->status !== StatusRequisicaoMaterial::Aberta) {
            throw ValidationException::withMessages([
                'status' => 'Somente requisições com status "Aberta" podem ser atendidas.',
            ]);
        }

        // Valida que o almoxarife pertence à unidade da RIM
        $pertenceAUnidade = $almoxarife->unidades()
            ->withoutGlobalScopes()
            ->where('unidades.id', $rim->unidade_id)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->exists();

        if (! $pertenceAUnidade) {
            throw ValidationException::withMessages([
                'almoxarife' => 'Almoxarife não pertence à unidade desta requisição.',
            ]);
        }

        return DB::transaction(function () use ($rim, $almoxarife) {
            // SaidaEstoqueAction pode lançar ValidationException (saldo insuficiente) — o rollback
            // é automático e a RIM permanece com status Aberta.
            // requisicaoMaterialId: a saída vincula TODAS as movimentações geradas (uma por lote
            // no FEFO multi-lote) à RIM, não só a âncora retornada — rastreabilidade completa
            // do ledger (Decisão 1 v1.1-C). $movimentacao é a última (âncora do rim).
            $movimentacao = $this->saidaEstoqueAction->execute(
                $rim->saldoEstoque,
                (float) $rim->quantidade_solicitada,
                "RIM #{$rim->id}: {$rim->justificativa}",
                $almoxarife,
                requisicaoMaterialId: $rim->id,
            );

            $rim->update([
                'status' => StatusRequisicaoMaterial::Atendida,
                'almoxarife_id' => $almoxarife->id,
                'movimentacao_estoque_id' => $movimentacao->id,
                'atendida_em' => now(),
            ]);

            return $rim->refresh();
        });
    }
}
