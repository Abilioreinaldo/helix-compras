<?php

namespace App\Actions;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcluirCotacaoAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar,
        private readonly IniciarAprovacaoAction $iniciarAprovacao
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao): void
    {
        DB::transaction(function () use ($requisicao) {
            $requisicao->refresh();

            $cotacoes = $requisicao->cotacoes()->whereNull('deleted_at')->get();

            // Só cotações com valor CONFIRMADO contam para o mínimo. Cotações "aguardando"
            // (valor null, criadas ao solicitar por e-mail) não contam até a compradora confirmar.
            $confirmadas = $cotacoes->whereNotNull('valor');

            // Via expressa e emergencial bastam 1 cotação (o preço homologado é a
            // evidência de preço); demais faixas exigem o mínimo configurado.
            $minimoNecessario = ($requisicao->expressa || $requisicao->is_emergencial)
                ? 1
                : ($requisicao->faixaAlcada?->minimo_cotacoes ?? 3);

            if ($confirmadas->count() < $minimoNecessario) {
                throw ValidationException::withMessages([
                    'cotacoes' => "São necessárias ao menos {$minimoNecessario} cotação(ões) com valor confirmado. Confirmadas: {$confirmadas->count()}.",
                ]);
            }

            $temVencedora = $cotacoes->where('vencedora', true)->count() === 1;

            if (! $temVencedora) {
                throw ValidationException::withMessages([
                    'cotacoes' => 'É necessário marcar exatamente uma cotação como vencedora.',
                ]);
            }

            $requisicao->update(['cotacao_concluida_em' => now()]);

            $this->transicionar->execute($requisicao, StatusRequisicao::CotacaoConcluida);
        });

        // Inicia aprovação fora da transação de cotação para que o e-mail
        // seja disparado apenas após o commit da cotação concluída.
        // Se falhar (ex.: sem aprovadores cadastrados), a cotação já está
        // comitada como CotacaoConcluida — o erro é reportado ao usuário
        // para que o admin corrija a configuração de alçadas.
        try {
            $this->iniciarAprovacao->execute($requisicao);
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            throw ValidationException::withMessages([
                'aprovacao' => "Cotação concluída, mas a aprovação não pôde ser iniciada: {$mensagem}",
            ]);
        }
    }
}
