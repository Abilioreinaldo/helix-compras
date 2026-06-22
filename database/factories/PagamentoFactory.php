<?php

namespace Database\Factories;

use App\Enums\StatusPagamento;
use App\Models\Fornecedor;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagamento>
 */
class PagamentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 100, 20000);

        return [
            'pedido_compra_id' => PedidoCompra::factory(),
            'fornecedor_id' => Fornecedor::factory(),
            'data_vencimento' => now()->addDays(fake()->numberBetween(5, 45))->toDateString(),
            'valor_total' => $total,
            'valor_pago' => 0,
            'status' => StatusPagamento::Pendente,
            'criado_por' => User::factory(),
        ];
    }

    public function vencido(): static
    {
        return $this->state(['data_vencimento' => now()->subDays(5)->toDateString()]);
    }

    public function pago(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => StatusPagamento::Pago,
            'valor_pago' => $attrs['valor_total'] ?? 100,
            'data_pagamento' => now()->toDateString(),
        ]);
    }
}
