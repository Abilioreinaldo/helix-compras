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
     * @throws ValidationException
     */
    public function execute(
        Requisicao $requisicao,
        Fornecedor $fornecedor,
        float $valor,
        ?UploadedFile $arquivo = null,
        ?int $prazoEntregaDias = null,
        ?string $observacoes = null,
        ?string $validadeProposta = null
    ): Cotacao {
        if (! $fornecedor->homologado || ! $fornecedor->ativo) {
            throw ValidationException::withMessages([
                'fornecedor_id' => 'O fornecedor não está homologado ou ativo.',
            ]);
        }

        return DB::transaction(function () use ($requisicao, $fornecedor, $valor, $arquivo, $prazoEntregaDias, $observacoes, $validadeProposta) {
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
