<?php

namespace Database\Factories;

use App\Enums\StatusObra;
use App\Models\Obra;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obra>
 */
class ObraFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'unidade_id' => Unidade::factory()->obra(),
            'iniciada_em' => $inicio->format('Y-m-d'),
            'previsao_termino' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'encerrada_em' => null,
            'status' => StatusObra::Ativa->value,
            'verba' => fake()->randomFloat(2, 50000, 5000000),
        ];
    }

    /**
     * Define a obra como encerrada.
     */
    public function encerrada(): static
    {
        return $this->state([
            'encerrada_em' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'status' => StatusObra::Encerrada->value,
        ]);
    }
}
