<?php

namespace Database\Factories;

use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoteEstoque>
 */
class LoteEstoqueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'saldo_estoque_id' => SaldoEstoque::factory(),
            'numero_lote' => strtoupper(fake()->bothify('??###')),
            'validade' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'quantidade' => fake()->randomFloat(3, 1, 500),
            'fundido_para_id' => null,
            'fundido_em' => null,
        ];
    }
}
