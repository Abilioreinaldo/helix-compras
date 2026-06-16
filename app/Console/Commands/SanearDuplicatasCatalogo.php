<?php

namespace App\Console\Commands;

use App\Actions\FusaoSaldosAction;
use App\Enums\Perfil;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SanearDuplicatasCatalogo extends Command
{
    protected $signature = 'estoque:sanear-duplicatas-catalogo
        {--dry-run : Lista os grupos de saldos duplicados sem executar a fusão}
        {--executado-por= : ID do usuário Admin que autoriza e executa a fusão}';

    protected $description = 'Funde saldos de estoque duplicados (mesma unidade/depósito/item de catálogo) num único saldo';

    public function __construct(private readonly FusaoSaldosAction $fusao)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $adminId = $this->option('executado-por');

        if (! $dryRun && $adminId === null) {
            $this->error('Informe --dry-run para simular ou --executado-por=<id> para executar a fusão.');

            return self::INVALID;
        }

        $grupos = $this->gruposDuplicados();

        if ($grupos->isEmpty()) {
            $this->info('Nenhum grupo de saldos duplicados encontrado. Nada a sanear.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            return $this->listar($grupos);
        }

        return $this->executar($grupos, $adminId);
    }

    /**
     * Grupos (unidade_id, deposito, item_catalogo_id) com mais de um saldo ATIVO
     * vinculado ao catálogo. Tombstones (fundido_para_id != null) são ignorados,
     * o que torna o comando idempotente.
     *
     * @return Collection<int, \stdClass>
     */
    private function gruposDuplicados(): Collection
    {
        return DB::table('saldos_estoque')
            ->whereNotNull('item_catalogo_id')
            ->whereNull('fundido_para_id')
            ->selectRaw('unidade_id, deposito, item_catalogo_id, COUNT(*) as total')
            ->groupBy('unidade_id', 'deposito', 'item_catalogo_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    /**
     * Saldos ativos de um grupo, ordenados por id (o menor será o destino).
     *
     * @return Collection<int, SaldoEstoque>
     */
    private function saldosDoGrupo(\stdClass $grupo): Collection
    {
        return SaldoEstoque::withoutGlobalScopes()
            ->where('unidade_id', $grupo->unidade_id)
            ->where('deposito', $grupo->deposito)
            ->where('item_catalogo_id', $grupo->item_catalogo_id)
            ->whereNull('fundido_para_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, \stdClass>  $grupos
     */
    private function listar(Collection $grupos): int
    {
        $linhas = [];

        foreach ($grupos as $grupo) {
            $ids = $this->saldosDoGrupo($grupo)->pluck('id')->sort()->values();

            $linhas[] = [
                $grupo->unidade_id,
                $grupo->deposito,
                $grupo->item_catalogo_id,
                $ids->count(),
                $ids->first(),
                $ids->implode(', '),
            ];
        }

        $this->table(
            ['Unidade', 'Depósito', 'Item Catálogo', 'Saldos', 'Destino (menor id)', 'IDs do grupo'],
            $linhas
        );

        $this->info($grupos->count().' grupo(s) duplicado(s) seriam fundidos. Nenhuma alteração foi feita (dry-run).');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, \stdClass>  $grupos
     */
    private function executar(Collection $grupos, string $adminId): int
    {
        $admin = User::find($adminId);

        if ($admin === null || ! $admin->temPerfil(Perfil::Admin)) {
            $this->error("Usuário #{$adminId} não encontrado ou não possui perfil Admin. Fusão abortada.");

            return self::FAILURE;
        }

        $fundidos = 0;

        foreach ($grupos as $grupo) {
            $saldos = $this->saldosDoGrupo($grupo);

            if ($saldos->count() < 2) {
                continue;
            }

            $destino = $this->fusao->fundir($saldos, $admin);
            $fundidos++;

            $this->line("Grupo item #{$grupo->item_catalogo_id} (depósito {$grupo->deposito}) fundido no saldo #{$destino->id}.");
        }

        $this->info("{$fundidos} grupo(s) de saldos duplicados fundido(s) por {$admin->name}.");

        return self::SUCCESS;
    }
}
