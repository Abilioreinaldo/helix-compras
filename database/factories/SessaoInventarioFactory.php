<?php

namespace Database\Factories;

use App\Enums\StatusInventario;
use App\Models\SessaoInventario;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessaoInventario>
 */
class SessaoInventarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'deposito' => fake()->randomElement(['Depósito Central', 'Depósito A', null]),
            'aberta_por' => User::factory(),
            'concluida_por' => null,
            'status' => StatusInventario::EmAndamento,
            'justificativa' => null,
            'concluida_em' => null,
        ];
    }

    public function emAndamento(): static
    {
        return $this->state(['status' => StatusInventario::EmAndamento]);
    }

    public function concluido(): static
    {
        return $this->state([
            'status' => StatusInventario::Concluido,
            'concluida_em' => now(),
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(['status' => StatusInventario::Cancelado]);
    }
}
