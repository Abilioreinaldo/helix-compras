<?php

namespace App\Actions;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use Illuminate\Validation\ValidationException;

class TransicionarStatusRequisicaoAction
{
    /** @var array<string, string[]> */
    private function transicoesPermitidas(): array
    {
        return [
            StatusRequisicao::Rascunho->value => [StatusRequisicao::AguardandoTriagem->value, StatusRequisicao::Cancelada->value],
            StatusRequisicao::AguardandoTriagem->value => [StatusRequisicao::EmTriagem->value, StatusRequisicao::Cancelada->value],
            StatusRequisicao::EmTriagem->value => [StatusRequisicao::EmCotacao->value, StatusRequisicao::Devolvida->value, StatusRequisicao::Cancelada->value],
            StatusRequisicao::Devolvida->value => [StatusRequisicao::AguardandoTriagem->value, StatusRequisicao::Cancelada->value],
            StatusRequisicao::EmCotacao->value => [StatusRequisicao::CotacaoConcluida->value, StatusRequisicao::Cancelada->value],
            StatusRequisicao::CotacaoConcluida->value => [StatusRequisicao::AguardandoAprovacao->value],
            StatusRequisicao::AguardandoAprovacao->value => [StatusRequisicao::Aprovada->value, StatusRequisicao::Reprovada->value],
            StatusRequisicao::Reprovada->value => [StatusRequisicao::EmCotacao->value],
            StatusRequisicao::Aprovada->value => [StatusRequisicao::EmCompra->value],
            StatusRequisicao::EmCompra->value => [StatusRequisicao::Recebida->value],
            StatusRequisicao::Recebida->value => [StatusRequisicao::Concluida->value],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function execute(
        Requisicao $requisicao,
        StatusRequisicao $novoStatus,
        ?string $observacao = null,
        bool $automatico = false
    ): void {
        $permitidas = $this->transicoesPermitidas()[$requisicao->status->value] ?? [];

        if (! in_array($novoStatus->value, $permitidas)) {
            throw ValidationException::withMessages([
                'status' => "Transição de '{$requisicao->status->value}' para '{$novoStatus->value}' não é permitida.",
            ]);
        }

        $statusAnterior = $requisicao->status->value;

        $extras = [];
        if ($novoStatus === StatusRequisicao::EmTriagem) {
            $extras['triagem_iniciada_em'] = now();
        }
        if ($novoStatus === StatusRequisicao::Cancelada) {
            $extras['cancelada_em'] = now();
            if (! $automatico) {
                $extras['cancelada_por'] = auth()->id();
            }
        }

        $requisicao->update(array_merge(['status' => $novoStatus], $extras));

        RequisicaoLog::create([
            'requisicao_id' => $requisicao->id,
            'status_anterior' => $statusAnterior,
            'status_novo' => $novoStatus->value,
            'user_id' => $automatico ? null : auth()->id(),
            'observacao' => $observacao,
            'automatico' => $automatico,
        ]);
    }
}
