<?php

namespace Database\Factories;

use App\Models\Banco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banco>
 */
class BancoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->randomElement(['Itaú', 'Bradesco', 'Banco do Brasil', 'Santander', 'Caixa']),
            'codigo_banco' => (string) fake()->unique()->numberBetween(1, 999),
            'ativo' => true,
        ];
    }
}
