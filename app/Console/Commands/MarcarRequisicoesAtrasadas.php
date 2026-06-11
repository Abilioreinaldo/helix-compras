<?php

namespace App\Console\Commands;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use Illuminate\Console\Command;

class MarcarRequisicoesAtrasadas extends Command
{
    protected $signature = 'requisicoes:marcar-atrasadas';

    protected $description = 'Marca como atrasadas as requisições aguardando triagem há mais de 24h';

    public function handle(): int
    {
        $requisicoes = Requisicao::withoutGlobalScopes()
            ->where('status', StatusRequisicao::AguardandoTriagem->value)
            ->where('submetida_em', '<', now()->subHours(24))
            ->where('atrasada', false)
            ->get();

        $total = 0;
        foreach ($requisicoes as $requisicao) {
            $requisicao->update(['atrasada' => true]);

            RequisicaoLog::create([
                'requisicao_id' => $requisicao->id,
                'status_anterior' => $requisicao->status->value,
                'status_novo' => $requisicao->status->value,
                'user_id' => null,
                'observacao' => 'Marcada como atrasada automaticamente (SLA 24h)',
                'automatico' => true,
            ]);

            $total++;
        }

        $this->info("{$total} requisição(ões) marcada(s) como atrasada(s).");

        return self::SUCCESS;
    }
}
