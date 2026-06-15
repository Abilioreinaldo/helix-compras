<?php

namespace Database\Factories;

use App\Models\Cotacao;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemPedidoCompra>
 */
class ItemPedidoCompraFactory extends Factory
{
    protected $model = ItemPedidoCompra::class;

    public function definition(): array
    {
        $qtd = fake()->randomFloat(2, 1, 100);
        $unit = fake()->randomFloat(2, 10, 500);

        return [
            'pedido_compra_id' => PedidoCompra::factory(),
            'requisicao_id' => Requisicao::factory(),
            'item_requisicao_id' => ItemRequisicao::factory(),
            'cotacao_id' => Cotacao::factory(),
            'descricao' => fake()->words(3, true),
            'quantidade' => $qtd,
            'unidade_medida' => fake()->randomElement(['un', 'kg', 'cx', 'lt']),
            'valor_unitario' => $unit,
            'valor_total' => round($qtd * $unit, 2),
            'destino' => fake()->optional()->company(),
        ];
    }
}
