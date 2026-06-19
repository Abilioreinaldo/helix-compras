<?php

namespace Database\Factories;

use App\Models\SaldoEstoque;
use App\Models\TransferenciaEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferenciaEstoque>
 */
class TransferenciaEstoqueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantidade = fake()->randomFloat(3, 1, 100);
        $custo = fake()->randomFloat(4, 1, 500);

        return [
            'saldo_origem_id' => SaldoEstoque::factory(),
            'saldo_destino_id' => SaldoEstoque::factory(),
            'unidade_destino_id' => Unidade::factory(),
            'quantidade' => $quantidade,
            'custo_unitario' => $custo,
            'valor_total' => round($quantidade * $custo, 2),
            'motivo' => fake()->sentence(),
            'executado_por' => User::factory(),
        ];
    }
}
