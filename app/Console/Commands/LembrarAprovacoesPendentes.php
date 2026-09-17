<?php

namespace App\Console\Commands;

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Mail\LembreteAprovacaoPendente;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Models\User;
use DateTimeInterface;
use Helix\Foundation\Console\Concerns\ForEachTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class LembrarAprovacoesPendentes extends Command
{
    use ForEachTenant;

    protected $signature = 'aprovacoes:lembrar-pendentes';

    protected $description = 'Envia lembrete por e-mail aos aprovadores de requisições aguardando aprovação há mais de 48h';

    public function handle(): int
    {
        $limite = now()->subHours(48);
        $reqsLembradas = 0;
        $emailsEnviados = 0;

        // Console não tem tenant no contexto: percorre tenant a tenant (modo estrito).
        $this->forEachTenant(function () use ($limite, &$reqsLembradas, &$emailsEnviados) {
            $this->lembrarNoTenant($limite, $reqsLembradas, $emailsEnviados);
        });

        $this->info("{$reqsLembradas} requisição(ões) pendente(s) há +48h — {$emailsEnviados} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }

    /** Lembra as pendências do tenant do contexto. */
    private function lembrarNoTenant(DateTimeInterface $limite, int &$reqsLembradas, int &$emailsEnviados): void
    {
        $requisicoes = Requisicao::withoutGlobalScope(UnidadeScope::class)
            ->with(['solicitante', 'unidade'])
            ->where('status', StatusRequisicao::AguardandoAprovacao->value)
            ->where('aprovacao_iniciada_em', '<', $limite)
            ->get();

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

            $aprovadores = $this->aprovadoresElegiveis($requisicao, $etapa->nivel_exigido->value);

            foreach ($aprovadores as $aprovador) {
                Mail::to($aprovador->email)->send(new LembreteAprovacaoPendente($requisicao, $aprovador));
                $emailsEnviados++;
            }

            if ($aprovadores->isNotEmpty()) {
                $reqsLembradas++;
            }
        }
    }

    /**
     * Aprovadores da unidade com o nível de alçada exigido pela etapa pendente.
     *
     * @return Collection<int, User>
     */
    private function aprovadoresElegiveis(Requisicao $requisicao, string $nivel): Collection
    {
        // ofTenant() (fundação v0.7.0): o recorte de gente é o VÍNCULO ATIVO em
        // `tenant_user` — `users.tenant_id` é só o tenant HOME. Ver AprovarEtapaAction.
        return User::ofTenant((string) $requisicao->tenant_id)
            ->whereIn('id', function ($q) use ($requisicao, $nivel) {
                $q->select('user_id')
                    ->from('unidade_user')
                    ->where('tenant_id', $requisicao->tenant_id)
                    ->where('unidade_id', $requisicao->unidade_id)
                    ->where('perfil', Perfil::Aprovador->value)
                    ->where('nivel_alcada', $nivel);
            })->get();
    }
}
