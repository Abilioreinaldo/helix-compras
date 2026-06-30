<?php

namespace App\Actions;

use App\Enums\OrigemCotacao;
use App\Enums\StatusRequisicao;
use App\Models\Cotacao;
use App\Models\PrecoHomologado;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atende uma requisição pela via expressa: gera uma cotação vencedora a partir
 * dos preços homologados (dispensando a cotação ad-hoc) e segue direto para a
 * aprovação por alçada — que permanece obrigatória, roteada pelo valor.
 */
class AtenderViaExpressaAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar,
        private readonly ConcluirCotacaoAction $concluirCotacao,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao, User $compradora): void
    {
        DB::transaction(function () use ($requisicao, $compradora) {
            $requisicao->refresh();

            if (! in_array($requisicao->status, [StatusRequisicao::AguardandoTriagem, StatusRequisicao::EmTriagem], true)) {
                throw ValidationException::withMessages([
                    'status' => 'A requisição precisa estar em triagem para ser atendida pela via expressa.',
                ]);
            }

            // Reavalia a elegibilidade no momento de atender — homologações podem
            // ter vencido desde a submissão. Não confiamos no flag para gerar valor.
            $avaliacao = $requisicao->avaliarViaExpressa();

            if ($avaliacao === null) {
                throw ValidationException::withMessages([
                    'expressa' => 'A requisição não está elegível à via expressa (item sem preço homologado válido ou fornecedores distintos). Use o fluxo de cotação.',
                ]);
            }

            if (! $requisicao->expressa) {
                $requisicao->update(['expressa' => true]);
            }

            if ($requisicao->status === StatusRequisicao::AguardandoTriagem) {
                $this->transicionar->execute($requisicao, StatusRequisicao::EmTriagem);
            }

            $this->transicionar->execute($requisicao, StatusRequisicao::EmCotacao);

            $this->gerarCotacaoHomologada($requisicao, $compradora, $avaliacao);
        });

        // Fora da transação: ConcluirCotacaoAction tem a sua própria transação e
        // dispara a aprovação (e e-mails) somente após o commit da cotação.
        $this->concluirCotacao->execute($requisicao);
    }

    /**
     * @param  array{fornecedor_id: int, precos: array<int, PrecoHomologado>}  $avaliacao
     */
    private function gerarCotacaoHomologada(Requisicao $requisicao, User $compradora, array $avaliacao): void
    {
        $itens = $requisicao->itens()->get()->keyBy('id');

        $valorTotal = 0.0;
        $validadeProposta = null;

        foreach ($avaliacao['precos'] as $itemId => $homologado) {
            $quantidade = (float) ($itens[$itemId]->quantidade ?? 0);
            $valorTotal += round((float) $homologado->preco * $quantidade, 2);

            if ($validadeProposta === null || $homologado->validade_fim->lt($validadeProposta)) {
                $validadeProposta = $homologado->validade_fim;
            }
        }

        $cotacao = Cotacao::create([
            'requisicao_id' => $requisicao->id,
            'origem' => OrigemCotacao::Homologado,
            'fornecedor_id' => $avaliacao['fornecedor_id'],
            'valor' => round($valorTotal, 2),
            'validade_proposta' => $validadeProposta,
            'observacoes' => 'Cotação gerada automaticamente a partir de preços homologados (via expressa).',
            'vencedora' => true,
            'criada_por' => $compradora->id,
            'vencedora_definida_em' => now(),
            'vencedora_definida_por' => $compradora->id,
        ]);

        foreach ($avaliacao['precos'] as $itemId => $homologado) {
            $cotacao->itensCotacao()->create([
                'item_requisicao_id' => $itemId,
                'valor_unitario' => $homologado->preco,
            ]);
        }
    }
}
