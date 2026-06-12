<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Mail\RequisicaoAguardandoAprovacao;
use App\Mail\RequisicaoAprovada;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AprovarEtapaAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao, User $aprovador, string $justificativa): void
    {
        $notificar = [];

        DB::transaction(function () use ($requisicao, $aprovador, $justificativa, &$notificar) {
            $requisicao->refresh();

            if ($requisicao->status !== StatusRequisicao::AguardandoAprovacao) {
                throw ValidationException::withMessages([
                    'status' => 'Esta requisição não está aguardando aprovação.',
                ]);
            }

            if ($aprovador->id === $requisicao->solicitante_id) {
                throw ValidationException::withMessages([
                    'aprovador' => 'O solicitante não pode aprovar a própria requisição.',
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
                'status' => StatusAprovacao::Aprovada->value,
                'aprovador_id' => $aprovador->id,
                'justificativa' => $justificativa,
                'decidida_em' => now(),
            ]);

            $proximaEtapa = Aprovacao::where('requisicao_id', $requisicao->id)
                ->where('ciclo', $requisicao->ciclo_aprovacao)
                ->where('status', StatusAprovacao::Pendente->value)
                ->orderBy('ordem')
                ->first();

            if ($proximaEtapa) {
                $aprovadoresProxima = $this->aprovadoresElegiveis($requisicao, $proximaEtapa->nivel_exigido->value);
                $notificar = [
                    'tipo' => 'aguardando',
                    'aprovadores' => $aprovadoresProxima->all(),
                ];
            } else {
                $requisicao->update(['aprovada_em' => now()]);
                $this->transicionar->execute($requisicao, StatusRequisicao::Aprovada, "Aprovada por {$aprovador->name}");
                $notificar = [
                    'tipo' => 'aprovada',
                    'solicitante' => $requisicao->solicitante,
                ];
            }
        });

        if (($notificar['tipo'] ?? null) === 'aguardando') {
            foreach ($notificar['aprovadores'] as $a) {
                Mail::to($a->email)->send(new RequisicaoAguardandoAprovacao($requisicao, $a));
            }
        } elseif (($notificar['tipo'] ?? null) === 'aprovada' && $notificar['solicitante']) {
            Mail::to($notificar['solicitante']->email)->send(new RequisicaoAprovada($requisicao));
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
                'aprovador' => 'Você não tem permissão para aprovar esta etapa.',
            ]);
        }
    }

    private function aprovadoresElegiveis(Requisicao $requisicao, string $nivel): Collection
    {
        return User::whereIn('id', function ($q) use ($requisicao, $nivel) {
            $q->select('user_id')
                ->from('unidade_user')
                ->where('unidade_id', $requisicao->unidade_id)
                ->where('perfil', Perfil::Aprovador->value)
                ->where('nivel_alcada', $nivel);
        })->get();
    }
}
