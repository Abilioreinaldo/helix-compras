<?php

namespace Database\Factories;

use App\Models\FaixaAlcada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaixaAlcada>
 */
class FaixaAlcadaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->randomFloat(2, 0, 10000);

        return [
            'nome' => fake()->words(3, true),
            'valor_minimo' => $min,
            'valor_maximo' => $min + fake()->randomFloat(2, 1000, 50000),
            'is_emergencial' => false,
            'ativo' => true,
        ];
    }

    /**
     * Define a faixa como emergencial.
     */
    public function emergencial(): static
    {
        return $this->state(['is_emergencial' => true]);
    }

    /**
     * Define a faixa sem teto de valor.
     */
    public function semTeto(): static
    {
        return $this->state(['valor_maximo' => null]);
    }
}
