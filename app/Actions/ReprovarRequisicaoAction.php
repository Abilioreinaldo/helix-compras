<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Mail\RequisicaoReprovada;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReprovarRequisicaoAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao, User $aprovador, string $justificativa): void
    {
        $compradoras = [];

        DB::transaction(function () use ($requisicao, $aprovador, $justificativa, &$compradoras) {
            $requisicao->refresh();

            if ($requisicao->status !== StatusRequisicao::AguardandoAprovacao) {
                throw ValidationException::withMessages([
                    'status' => 'Esta requisição não está aguardando aprovação.',
                ]);
            }

            if ($aprovador->id === $requisicao->solicitante_id) {
                throw ValidationException::withMessages([
                    'aprovador' => 'O solicitante não pode reprovar a própria requisição.',
                ]);
            }

            $etapaAtual = Aprovacao::where('requisicao_id', $requisicao->id)
                ->where('ciclo', $requisicao->ciclo_aprovacao)
                ->where('status', StatusAprovacao::Pendente->value)
                ->orderBy('ordem')
                ->lockForUpdate()
                ->first();

            if (! $etapaAtual) {
                throw ValidationException::withMessages([
                    'etapa' => 'Não há etapa de aprovação pendente para esta requisição.',
                ]);
            }

            $this->validarPermissao($aprovador, $requisicao, $etapaAtual);

            $etapaAtual->update([
                'status' => StatusAprovacao::Reprovada->value,
                'aprovador_id' => $aprovador->id,
                'justificativa' => $justificativa,
                'decidida_em' => now(),
            ]);

            Aprovacao::where('requisicao_id', $requisicao->id)
                ->where('ciclo', $requisicao->ciclo_aprovacao)
                ->where('status', StatusAprovacao::Pendente->value)
                ->update(['status' => StatusAprovacao::Pulada->value]);

            $requisicao->update([
                'reprovada_em' => now(),
                'reprovada_por' => $aprovador->id,
                'ciclo_aprovacao' => ($requisicao->ciclo_aprovacao ?? 1) + 1,
            ]);

            $this->transicionar->execute($requisicao, StatusRequisicao::Reprovada, $justificativa);
            $this->transicionar->execute($requisicao, StatusRequisicao::EmCotacao, 'Retornada à cotação após reprovação.');

            $compradoras = User::whereHas('roles', fn ($q) => $q->where('slug', 'compras'))->get()->all();
        });

        foreach ($compradoras as $compradora) {
            Mail::to($compradora->email)->send(new RequisicaoReprovada($requisicao, $aprovador, $justificativa));
        }
    }

    private function validarPermissao(User $aprovador, Requisicao $requisicao, Aprovacao $etapa): void
    {
        $temPermissao = $aprovador->unidades()
            ->withoutGlobalScopes()
            ->where('unidades.id', $requisicao->unidade_id)
            ->wherePivot('perfil', Perfil::Aprovador->value)
            ->wherePivot('nivel_alcada', $etapa->nivel_exigido->value)
            ->exists();

        if (! $temPermissao) {
            throw ValidationException::withMessages([
                'aprovador' => 'Você não tem permissão para reprovar esta etapa.',
            ]);
        }
    }
}
