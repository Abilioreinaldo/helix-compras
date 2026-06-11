<?php

namespace App\Actions;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcluirCotacaoAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao): void
    {
        DB::transaction(function () use ($requisicao) {
            $requisicao->refresh();

            $cotacoes = $requisicao->cotacoes()->whereNull('deleted_at')->get();

            $minimoNecessario = $requisicao->is_emergencial ? 1 : ($requisicao->faixaAlcada?->minimo_cotacoes ?? 3);

            if ($cotacoes->count() < $minimoNecessario) {
                throw ValidationException::withMessages([
                    'cotacoes' => "São necessárias ao menos {$minimoNecessario} cotação(ões). Registradas: {$cotacoes->count()}.",
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
    }
}
