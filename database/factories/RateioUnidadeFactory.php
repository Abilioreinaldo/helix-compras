<?php

namespace Database\Factories;

use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateioUnidade>
 */
class RateioUnidadeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rateio_central_id' => RateioCentral::factory(),
            'unidade_id' => Unidade::factory(),
            'percentual_consumo' => fake()->randomFloat(4, 0, 1),
            'valor_rateado' => fake()->randomFloat(2, 0, 10000),
        ];
    }
}
