<?php

namespace Database\Factories;

use App\Models\Banco;
use App\Models\ReconciliacaoBancaria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliacaoBancaria>
 */
class ReconciliacaoBancariaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'banco_id' => Banco::factory(),
            'data_arquivo' => now()->toDateString(),
            'total_linhas' => 0,
            'total_processado' => 0,
            'total_conciliado' => 0,
            'arquivo_hash' => fake()->unique()->sha256(),
            'criado_por' => User::factory(),
        ];
    }
}
