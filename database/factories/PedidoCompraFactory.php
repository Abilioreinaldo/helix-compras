<?php

namespace Database\Factories;

use App\Enums\StatusPedidoCompra;
use App\Models\Fornecedor;
use App\Models\PedidoCompra;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoCompra>
 */
class PedidoCompraFactory extends Factory
{
    protected $model = PedidoCompra::class;

    public function definition(): array
    {
        return [
            'status' => StatusPedidoCompra::Rascunho,
            'fornecedor_id' => Fornecedor::factory(),
            'unidade_id' => Unidade::factory(),
            'criado_por' => User::factory(),
            'condicoes_pagamento' => null,
            'observacoes' => null,
            'numero' => null,
            'ano' => null,
            'sequencia' => null,
            'emitido_em' => null,
            'emitido_por' => null,
            'cancelado_em' => null,
            'cancelado_por' => null,
            'motivo_cancelamento' => null,
        ];
    }

    public function emitido(): static
    {
        $ano = 2026;
        $seq = fake()->unique()->numberBetween(1, 9999);

        return $this->state([
            'status' => StatusPedidoCompra::Emitido,
            'numero' => sprintf('PC-%04d-%04d', $ano, $seq),
            'ano' => $ano,
            'sequencia' => $seq,
            'emitido_em' => now(),
            'emitido_por' => User::factory(),
        ]);
    }

    public function cancelado(): static
    {
        return $this->state([
            'status' => StatusPedidoCompra::Cancelado,
            'cancelado_em' => now(),
            'cancelado_por' => User::factory(),
            'motivo_cancelamento' => fake()->sentence(),
        ]);
    }
}
