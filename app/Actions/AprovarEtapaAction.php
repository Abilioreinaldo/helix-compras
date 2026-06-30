<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Mail\RequisicaoAguardandoAprovacao;
use App\Mail\RequisicaoAprovada;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
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
     * @param  array<int, string>  $itensRejeitados  Mapa item_id => motivo dos itens
     *                                               rejeitados na decisão por linha.
     *
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao, User $aprovador, string $justificativa, array $itensRejeitados = []): void
    {
        $notificar = [];

        DB::transaction(function () use ($requisicao, $aprovador, $justificativa, $itensRejeitados, &$notificar) {
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

            $this->aplicarRejeicoesPorLinha($requisicao, $aprovador, $itensRejeitados);

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

    /**
     * Decisão por linha: marca os itens indicados como rejeitados (com motivo) e
     * os exclui da compra. A cadeia de alçada NÃO é encurtada — rejeitar itens
     * reduz custo, nunca remove etapas (impede burlar a alçada por fracionamento).
     *
     * @param  array<int, string>  $itensRejeitados
     *
     * @throws ValidationException
     */
    private function aplicarRejeicoesPorLinha(Requisicao $requisicao, User $aprovador, array $itensRejeitados): void
    {
        if (empty($itensRejeitados)) {
            return;
        }

        $ativos = $requisicao->itens()->whereNull('rejeitado_em')->lockForUpdate()->get()->keyBy('id');
        $ids = array_map('intval', array_keys($itensRejeitados));

        foreach ($ids as $id) {
            if (! $ativos->has($id)) {
                throw ValidationException::withMessages([
                    'itens' => 'Item inválido ou já rejeitado.',
                ]);
            }
            if (trim((string) ($itensRejeitados[$id] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'itens' => 'Informe o motivo da rejeição de cada item.',
                ]);
            }
        }

        // Não pode rejeitar TODOS os itens — isso seria reprovar a requisição.
        if (count($ids) >= $ativos->count()) {
            throw ValidationException::withMessages([
                'itens' => 'Não é possível rejeitar todos os itens. Para isso, reprove a requisição.',
            ]);
        }

        foreach ($ids as $id) {
            $item = $ativos->get($id);
            $motivo = trim((string) $itensRejeitados[$id]);

            $item->update([
                'rejeitado_em' => now(),
                'rejeitado_por' => $aprovador->id,
                'motivo_rejeicao' => $motivo,
            ]);

            RequisicaoLog::create([
                'requisicao_id' => $requisicao->id,
                'status_anterior' => $requisicao->status->value,
                'status_novo' => $requisicao->status->value,
                'user_id' => $aprovador->id,
                'observacao' => "Item rejeitado na aprovação: \"{$item->descricao}\" — {$motivo}",
                'automatico' => false,
            ]);
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
