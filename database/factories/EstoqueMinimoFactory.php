<?php

namespace Database\Factories;

use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstoqueMinimo>
 */
class EstoqueMinimoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'item_catalogo_id' => CatalogoItem::factory(),
            // quantidade_minima sempre > 0 (mínimo = 0 remove o registro)
            'quantidade_minima' => fake()->randomFloat(3, 1, 500),
        ];
    }
}
