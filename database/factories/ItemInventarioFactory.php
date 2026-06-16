<?php

namespace Database\Factories;

use App\Models\ItemInventario;
use App\Models\SaldoEstoque;
use App\Models\SessaoInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemInventario>
 */
class ItemInventarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sessao_inventario_id' => SessaoInventario::factory(),
            'saldo_estoque_id' => SaldoEstoque::factory(),
            'quantidade_sistema' => fake()->randomFloat(3, 0, 100),
            'quantidade_contada' => null,
            'movimentacao_estoque_id' => null,
        ];
    }

    public function contado(float $quantidade): static
    {
        return $this->state(['quantidade_contada' => $quantidade]);
    }
}
