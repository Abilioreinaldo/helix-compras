<?php

namespace Database\Seeders;

use App\Models\CentroCusto;
use App\Models\Scopes\UnidadeScope;
use App\Models\Unidade;
use Illuminate\Database\Seeder;

class CentroCustoSeeder extends Seeder
{
    /**
     * Cria 2 centros de custo por unidade.
     */
    public function run(): void
    {
        $unidades = Unidade::withoutGlobalScope(UnidadeScope::class)->get();

        foreach ($unidades as $unidade) {
            CentroCusto::withoutGlobalScope(UnidadeScope::class)->create([
                'unidade_id' => $unidade->id,
                'codigo' => 'CC-001',
                'nome' => 'Operacional',
                'gestor_id' => $unidade->gestor_id,
                'ativo' => true,
            ]);

            CentroCusto::withoutGlobalScope(UnidadeScope::class)->create([
                'unidade_id' => $unidade->id,
                'codigo' => 'CC-002',
                'nome' => 'Administrativo',
                'gestor_id' => null,
                'ativo' => true,
            ]);
        }
    }
}
