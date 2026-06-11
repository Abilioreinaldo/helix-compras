<?php

namespace Database\Factories;

use App\Models\ItemRequisicao;
use App\Models\Requisicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemRequisicao>
 */
class ItemRequisicaoFactory extends Factory
{
    protected $model = ItemRequisicao::class;

    public function definition(): array
    {
        return [
            'requisicao_id' => Requisicao::factory(),
            'descricao' => fake()->words(3, true),
            'quantidade' => fake()->randomFloat(3, 1, 100),
            'unidade_medida' => fake()->randomElement(['un', 'kg', 'L', 'm']),
            'valor_unitario_estimado' => fake()->randomFloat(2, 10, 1000),
        ];
    }
}
