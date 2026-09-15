<?php

namespace App\Console\Commands;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use App\Models\Scopes\UnidadeScope;
use Helix\Foundation\Console\Concerns\ForEachTenant;
use Illuminate\Console\Command;

class MarcarRequisicoesAtrasadas extends Command
{
    use ForEachTenant;

    protected $signature = 'requisicoes:marcar-atrasadas';

    protected $description = 'Marca como atrasadas as requisições aguardando triagem há mais de 24h';

    public function handle(): int
    {
        $total = 0;

        // Console não tem tenant no contexto: percorre tenant a tenant (modo estrito) —
        // o log automático nasce carimbado com o tenant da requisição.
        $this->forEachTenant(function () use (&$total) {
            $requisicoes = Requisicao::withoutGlobalScope(UnidadeScope::class)
                ->where('status', StatusRequisicao::AguardandoTriagem->value)
                ->where('submetida_em', '<', now()->subHours(24))
                ->where('atrasada', false)
                ->get();

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
        });

        $this->info("{$total} requisição(ões) marcada(s) como atrasada(s).");

        return self::SUCCESS;
    }
}
