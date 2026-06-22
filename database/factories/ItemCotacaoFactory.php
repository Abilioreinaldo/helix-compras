<?php

namespace Database\Factories;

use App\Models\Cotacao;
use App\Models\ItemCotacao;
use App\Models\ItemRequisicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCotacao>
 */
class ItemCotacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cotacao_id' => Cotacao::factory(),
            'item_requisicao_id' => ItemRequisicao::factory(),
            'valor_unitario' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
