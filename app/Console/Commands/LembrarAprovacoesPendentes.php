<?php

namespace App\Console\Commands;

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Mail\LembreteAprovacaoPendente;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class LembrarAprovacoesPendentes extends Command
{
    protected $signature = 'aprovacoes:lembrar-pendentes';

    protected $description = 'Envia lembrete por e-mail aos aprovadores de requisições aguardando aprovação há mais de 48h';

    public function handle(): int
    {
        $limite = now()->subHours(48);

        $requisicoes = Requisicao::withoutGlobalScopes()
            ->with(['solicitante', 'unidade'])
            ->where('status', StatusRequisicao::AguardandoAprovacao->value)
            ->where('aprovacao_iniciada_em', '<', $limite)
            ->get();

        $reqsLembradas = 0;
        $emailsEnviados = 0;

        foreach ($requisicoes as $requisicao) {
            // Etapa pendente atual: menor ordem ainda Pendente no ciclo de aprovação vigente.
            $etapa = Aprovacao::where('requisicao_id', $requisicao->id)
                ->where('ciclo', $requisicao->ciclo_aprovacao ?? 1)
                ->where('status', StatusAprovacao::Pendente->value)
                ->orderBy('ordem')
                ->first();

            if ($etapa === null) {
                continue; // sem etapa pendente (estado inconsistente) — não lembra
            }

            $aprovadores = $this->aprovadoresElegiveis($requisicao->unidade_id, $etapa->nivel_exigido->value);

            foreach ($aprovadores as $aprovador) {
                Mail::to($aprovador->email)->send(new LembreteAprovacaoPendente($requisicao, $aprovador));
                $emailsEnviados++;
            }

            if ($aprovadores->isNotEmpty()) {
                $reqsLembradas++;
            }
        }

        $this->info("{$reqsLembradas} requisição(ões) pendente(s) há +48h — {$emailsEnviados} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }

    /**
     * Aprovadores da unidade com o nível de alçada exigido pela etapa pendente.
     *
     * @return Collection<int, User>
     */
    private function aprovadoresElegiveis(int $unidadeId, string $nivel): Collection
    {
        return User::whereIn('id', function ($q) use ($unidadeId, $nivel) {
            $q->select('user_id')
                ->from('unidade_user')
                ->where('unidade_id', $unidadeId)
                ->where('perfil', Perfil::Aprovador->value)
                ->where('nivel_alcada', $nivel);
        })->get();
    }
}
