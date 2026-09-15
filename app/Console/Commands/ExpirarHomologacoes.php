<?php

namespace App\Console\Commands;

use App\Models\PrecoHomologado;
use Helix\Foundation\Console\Concerns\ForEachTenant;
use Illuminate\Console\Command;

class ExpirarHomologacoes extends Command
{
    use ForEachTenant;

    protected $signature = 'precos:expirar-homologacoes';

    protected $description = 'Desativa preços homologados cuja validade já venceu (housekeeping da via expressa)';

    public function handle(): int
    {
        // Filtro de data por bind (string), sem função de dialeto — portável SQLite↔MySQL.
        $hoje = now()->toDateString();
        $total = 0;

        // Console não tem tenant no contexto: percorre tenant a tenant (modo estrito).
        $this->forEachTenant(function () use ($hoje, &$total) {
            $total += PrecoHomologado::where('ativo', true)
                ->where('validade_fim', '<', $hoje)
                ->update(['ativo' => false]);
        });

        $this->info("{$total} preço(s) homologado(s) vencido(s) desativado(s).");

        return self::SUCCESS;
    }
}
