<?php

namespace App\Console\Commands;

use App\Models\PrecoHomologado;
use Illuminate\Console\Command;

class ExpirarHomologacoes extends Command
{
    protected $signature = 'precos:expirar-homologacoes';

    protected $description = 'Desativa preços homologados cuja validade já venceu (housekeeping da via expressa)';

    public function handle(): int
    {
        // Filtro de data por bind (string), sem função de dialeto — portável SQLite↔MySQL.
        $hoje = now()->toDateString();

        $total = PrecoHomologado::where('ativo', true)
            ->where('validade_fim', '<', $hoje)
            ->update(['ativo' => false]);

        $this->info("{$total} preço(s) homologado(s) vencido(s) desativado(s).");

        return self::SUCCESS;
    }
}
