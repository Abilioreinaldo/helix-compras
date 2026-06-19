<?php

namespace Database\Factories;

use App\Models\RateioCentral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateioCentral>
 */
class RateioCentralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mes' => fake()->numberBetween(1, 12),
            'ano' => 2026,
            'valor_total' => fake()->randomFloat(2, 1000, 50000),
            'criado_por' => User::factory(),
        ];
    }
}
