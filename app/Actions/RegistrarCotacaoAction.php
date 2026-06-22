<?php

namespace App\Actions;

use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarCotacaoAction
{
    /**
     * Registra uma cotação de fornecedor.
     *
     * Quando $precosPorItem é informado (matriz: [item_requisicao_id => valor_unitario]),
     * cria os ItemCotacao e o total ($valor) passa a ser a SOMA das linhas
     * (valor_unitario × quantidade). Sem ele, usa o $valor total recebido (legado).
     *
     * @param  array<int|string, float|string>|null  $precosPorItem
     *
     * @throws ValidationException
     */
    public function execute(
        Requisicao $requisicao,
        Fornecedor $fornecedor,
        float $valor,
        ?UploadedFile $arquivo = null,
        ?int $prazoEntregaDias = null,
        ?string $observacoes = null,
        ?string $validadeProposta = null,
        ?array $precosPorItem = null,
    ): Cotacao {
        if (! $fornecedor->homologado || ! $fornecedor->ativo) {
            throw ValidationException::withMessages([
                'fornecedor_id' => 'O fornecedor não está homologado ou ativo.',
            ]);
        }

        // Caminho por item: valida ids contra a requisição e recalcula o total.
        $linhas = [];
        if ($precosPorItem !== null) {
            $itensReq = $requisicao->itens()->get()->keyBy('id');
            $valor = 0.0;

            foreach ($precosPorItem as $itemId => $unitario) {
                $item = $itensReq->get((int) $itemId);
                if (! $item) {
                    continue; // ignora ids que não pertencem à requisição
                }
                $unitario = round((float) $unitario, 2);
                if ($unitario <= 0.0) {
                    continue; // descarta preço zero/negativo (não distorce o mapa nem o total)
                }
                $linhas[(int) $itemId] = $unitario;
                // Soma de linhas já arredondadas → o total bate com a soma exibida no mapa.
                $valor += round($unitario * (float) $item->quantidade, 2);
            }

            $valor = round($valor, 2);

            if ($linhas === [] || $valor <= 0.0) {
                throw ValidationException::withMessages([
                    'precos' => 'Informe um preço válido (maior que zero) para ao menos um item.',
                ]);
            }
        }

        return DB::transaction(function () use ($requisicao, $fornecedor, $valor, $arquivo, $prazoEntregaDias, $observacoes, $validadeProposta, $linhas) {
            $arquivoPath = null;
            $arquivoNomeOriginal = null;

            if ($arquivo) {
                $arquivoNomeOriginal = $arquivo->getClientOriginalName();
                $arquivoPath = $arquivo->store('cotacoes', 'local');
            }

            $cotacao = Cotacao::create([
                'requisicao_id' => $requisicao->id,
                'fornecedor_id' => $fornecedor->id,
                'valor' => $valor,
                'prazo_entrega_dias' => $prazoEntregaDias,
                'validade_proposta' => $validadeProposta ?: null,
                'arquivo_path' => $arquivoPath,
                'arquivo_nome_original' => $arquivoNomeOriginal,
                'observacoes' => $observacoes,
                'vencedora' => false,
                'criada_por' => auth()->id(),
            ]);

            foreach ($linhas as $itemId => $unitario) {
                $cotacao->itensCotacao()->create([
                    'item_requisicao_id' => $itemId,
                    'valor_unitario' => $unitario,
                ]);
            }

            if ($requisicao->primeira_cotacao_em === null) {
                $requisicao->update(['primeira_cotacao_em' => now()]);
            }

            RequisicaoLog::create([
                'requisicao_id' => $requisicao->id,
                'status_anterior' => $requisicao->status->value,
                'status_novo' => $requisicao->status->value,
                'user_id' => auth()->id(),
                'observacao' => "Cotação registrada: {$fornecedor->nome_fantasia} — R$ ".number_format($valor, 2, ',', '.'),
                'automatico' => false,
            ]);

            return $cotacao;
        });
    }
}
