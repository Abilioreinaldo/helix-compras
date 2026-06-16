<?php

namespace Database\Factories;

use App\Models\CatalogoItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogoItem>
 */
class CatalogoItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'codigo' => fake()->unique()->bothify('ITM-####'),
            'descricao' => fake()->words(3, true),
            'unidade_medida' => fake()->randomElement(['un', 'cx', 'kg', 'l', 'pc']),
            'categoria' => fake()->randomElement(['material de escritório', 'epi', 'ferramentas', 'limpeza', null]),
            'ativo' => true,
        ];
    }

    /**
     * Item inativo (descontinuado do catálogo).
     */
    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
