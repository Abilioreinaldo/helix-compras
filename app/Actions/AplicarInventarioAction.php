<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusInventario;
use App\Enums\TipoMovimentacao;
use App\Models\SessaoInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AplicarInventarioAction
{
    public function __construct(private readonly AjusteEstoqueAction $ajusteEstoqueAction) {}

    /**
     * Aplica o inventário, gerando ajustes para divergências encontradas.
     *
     * @throws ValidationException
     */
    public function execute(SessaoInventario $sessao, string $justificativa, User $aplicadoPor): SessaoInventario
    {
        if ($sessao->status !== StatusInventario::EmAndamento) {
            throw ValidationException::withMessages([
                'status' => 'Somente sessões em andamento podem ser aplicadas.',
            ]);
        }

        if (trim($justificativa) === '') {
            throw ValidationException::withMessages([
                'justificativa' => 'A justificativa é obrigatória para aplicar o inventário.',
            ]);
        }

        // Valida perfil: Admin (global) ou Almoxarife da unidade
        $autorizado = $aplicadoPor->temPerfil(Perfil::Admin)
            || $aplicadoPor->unidades()
                ->withoutGlobalScopes()
                ->where('unidades.id', $sessao->unidade_id)
                ->wherePivot('perfil', Perfil::Almoxarife->value)
                ->exists();

        if (! $autorizado) {
            abort(403, 'Perfil insuficiente para aplicar inventário.');
        }

        // Verifica se todos os itens estão contados
        $sessao->load('itens.saldoEstoque');

        $naoContados = $sessao->itens->filter(fn ($item) => $item->quantidade_contada === null)->count();

        if ($naoContados > 0) {
            throw ValidationException::withMessages([
                'itens' => "Existem {$naoContados} item(ns) sem quantidade contada. Preencha todos antes de aplicar.",
            ]);
        }

        return DB::transaction(function () use ($sessao, $justificativa, $aplicadoPor) {
            foreach ($sessao->itens as $item) {
                $divergencia = (float) $item->quantidade_contada - (float) $item->quantidade_sistema;

                if (abs($divergencia) <= 0.001) {
                    continue;
                }

                $tipo = $divergencia > 0
                    ? TipoMovimentacao::AjustePositivo
                    : TipoMovimentacao::AjusteNegativo;

                $movimentacao = $this->ajusteEstoqueAction->execute(
                    $item->saldoEstoque,
                    $tipo,
                    abs($divergencia),
                    "Inventário #{$sessao->id}: {$justificativa}",
                    $aplicadoPor,
                );

                $item->update(['movimentacao_estoque_id' => $movimentacao->id]);
            }

            $sessao->update([
                'status' => StatusInventario::Concluido,
                'concluida_por' => $aplicadoPor->id,
                'justificativa' => $justificativa,
                'concluida_em' => now(),
            ]);

            return $sessao->refresh();
        });
    }
}
