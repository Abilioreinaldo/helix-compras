<?php

namespace Database\Seeders;

use App\Models\CentroCusto;
use App\Models\Unidade;
use Illuminate\Database\Seeder;

class CentroCustoSeeder extends Seeder
{
    /**
     * Cria 2 centros de custo por unidade.
     */
    public function run(): void
    {
        $unidades = Unidade::withoutGlobalScopes()->get();

        foreach ($unidades as $unidade) {
            CentroCusto::withoutGlobalScopes()->create([
                'unidade_id' => $unidade->id,
                'codigo' => 'CC-001',
                'nome' => 'Operacional',
                'gestor_id' => $unidade->gestor_id,
                'ativo' => true,
            ]);

            CentroCusto::withoutGlobalScopes()->create([
                'unidade_id' => $unidade->id,
                'codigo' => 'CC-002',
                'nome' => 'Administrativo',
                'gestor_id' => null,
                'ativo' => true,
            ]);
        }
    }
}
