<?php

namespace Database\Factories;

use App\Models\SaldoEstoque;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaldoEstoque>
 */
class SaldoEstoqueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantidade = fake()->randomFloat(3, 0, 1000);
        $cmp = fake()->randomFloat(4, 1, 500);

        return [
            'unidade_id' => Unidade::factory(),
            'deposito' => fake()->randomElement(['Depósito Central', 'Depósito A', 'Depósito B']),
            'descricao_item' => fake()->words(3, true),
            'descricao_normalizada' => fn (array $attrs) => SaldoEstoque::normalizarDescricao($attrs['descricao_item']),
            'unidade_medida' => fake()->randomElement(['un', 'cx', 'kg', 'l', 'pc']),
            'quantidade' => $quantidade,
            'custo_medio_ponderado' => $cmp,
            'valor_total' => $quantidade * $cmp,
            'item_catalogo_id' => null,
            'fundido_para_id' => null,
            'fundido_em' => null,
        ];
    }
}
