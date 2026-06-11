<?php

namespace Database\Factories;

use App\Models\CentroCusto;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CentroCusto>
 */
class CentroCustoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'codigo' => strtoupper(fake()->lexify('CC-???')).'-'.fake()->numerify('###'),
            'nome' => fake()->words(3, true),
            'gestor_id' => null,
            'ativo' => true,
        ];
    }

    /**
     * Centro de custo inativo.
     */
    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
