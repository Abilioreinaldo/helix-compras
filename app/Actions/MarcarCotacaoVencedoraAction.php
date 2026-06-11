<?php

namespace App\Actions;

use App\Models\Cotacao;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use Illuminate\Support\Facades\DB;

class MarcarCotacaoVencedoraAction
{
    public function execute(Requisicao $requisicao, Cotacao $cotacao): void
    {
        DB::transaction(function () use ($requisicao, $cotacao) {
            Cotacao::where('requisicao_id', $requisicao->id)
                ->where('id', '!=', $cotacao->id)
                ->update([
                    'vencedora' => false,
                    'vencedora_definida_em' => null,
                    'vencedora_definida_por' => null,
                ]);

            $cotacao->update([
                'vencedora' => true,
                'vencedora_definida_em' => now(),
                'vencedora_definida_por' => auth()->id(),
            ]);

            RequisicaoLog::create([
                'requisicao_id' => $requisicao->id,
                'status_anterior' => $requisicao->status->value,
                'status_novo' => $requisicao->status->value,
                'user_id' => auth()->id(),
                'observacao' => "Cotação vencedora definida: {$cotacao->fornecedor->nome_fantasia} — R$ ".number_format((float) $cotacao->valor, 2, ',', '.'),
                'automatico' => false,
            ]);
        });
    }
}
