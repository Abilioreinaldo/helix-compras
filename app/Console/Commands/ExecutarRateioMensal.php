<?php

namespace App\Console\Commands;

use App\Actions\CalcularRateioMensalAction;
use App\Enums\Perfil;
use App\Models\RateioCentral;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExecutarRateioMensal extends Command
{
    protected $signature = 'rateio:executar-mensal
        {--valor-central= : Valor total do custo da central a ratear (R$). Se omitido, é solicitado.}
        {--mes= : Mês a ratear (1-12). Default: mês anterior.}
        {--ano= : Ano a ratear. Default: ano do mês anterior.}
        {--executado-por= : ID do usuário Admin que executa o rateio.}';

    protected $description = 'Calcula o rateio mensal do custo da central por unidade (consumo proporcional).';

    public function __construct(private readonly CalcularRateioMensalAction $calcular)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $adminId = $this->option('executado-por');

        if ($adminId === null) {
            $this->error('Informe --executado-por=<id do Admin> que autoriza o rateio.');

            return self::INVALID;
        }

        $admin = User::find($adminId);
        $tenantAlvo = $admin === null ? '' : (string) $admin->getAttributes()['tenant_id'];

        // Identidade GLOBAL ativa (users.status) + vínculo ativo de admin NO TENANT em que
        // o rateio vai rodar: a autoria gravada (criado_por/registrado_por) nunca é de quem
        // foi suspenso, e a autoridade é a daquele cliente.
        //
        // COMPRAS-7 (4ª auditoria) / fundação v0.7.0: aqui não há tenant no contexto, e
        // `temPerfil()` passou a NEGAR nessa situação (antes respondia pelo tenant home).
        // A pergunta explícita `temPerfilEm($tenant)` é a que diz o que este comando quer.
        if ($admin === null || $admin->status !== 'active' || ! $admin->temPerfilEm(Perfil::Admin, $tenantAlvo)) {
            $this->error("Usuário #{$adminId} não encontrado, inativo ou sem perfil Admin. Rateio abortado.");

            return self::FAILURE;
        }

        // Console não tem tenant no contexto: o rateio roda no tenant do Admin executor
        // (modo estrito — consulta/criação sem tenant lança).
        return TenantContext::runFor((string) $tenantAlvo, fn () => $this->executarNoTenant($admin));
    }

    private function executarNoTenant(User $admin): int
    {
        // Default: mês anterior (subMonthNoOverflow trata a virada de ano corretamente).
        $ref = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $mes = $this->option('mes') !== null ? (int) $this->option('mes') : $ref->month;
        $ano = $this->option('ano') !== null ? (int) $this->option('ano') : $ref->year;

        if (RateioCentral::where('mes', $mes)->where('ano', $ano)->exists()) {
            $this->warn("Já existe rateio para {$mes}/{$ano}. Nada a fazer (idempotente).");

            return self::SUCCESS;
        }

        $valorOpcao = $this->option('valor-central');
        $valorCentral = $valorOpcao !== null
            ? (float) str_replace(',', '.', (string) $valorOpcao)
            : (float) str_replace(',', '.', (string) $this->ask('Valor total da central a ratear (R$)'));

        try {
            $rateio = $this->calcular->execute($mes, $ano, $valorCentral, $admin);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?? 'Falha ao calcular o rateio.');

            return self::FAILURE;
        }

        return $this->resumo($rateio);
    }

    private function resumo(RateioCentral $rateio): int
    {
        $linhas = $rateio->unidades;
        $comRateio = $linhas->where('valor_rateado', '>', 0);

        $this->info("Rateio {$rateio->mes}/{$rateio->ano} concluído.");
        $this->line('  Unidades rateadas: '.$comRateio->count().' de '.$linhas->count());
        $this->line('  Total rateado: R$ '.number_format((float) $linhas->sum('valor_rateado'), 2, ',', '.'));

        if ($comRateio->isNotEmpty()) {
            $maior = $comRateio->sortByDesc('percentual_consumo')->first();
            $menor = $comRateio->sortBy('percentual_consumo')->first();
            $this->line('  Maior %: unidade #'.$maior->unidade_id.' = '.number_format((float) $maior->percentual_consumo * 100, 2, ',', '.').'%');
            $this->line('  Menor %: unidade #'.$menor->unidade_id.' = '.number_format((float) $menor->percentual_consumo * 100, 2, ',', '.').'%');
        }

        return self::SUCCESS;
    }
}
