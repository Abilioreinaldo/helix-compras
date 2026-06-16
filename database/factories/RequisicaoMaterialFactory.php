<?php

namespace Database\Factories;

use App\Enums\StatusRequisicaoMaterial;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequisicaoMaterial>
 */
class RequisicaoMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'solicitante_id' => User::factory(),
            'almoxarife_id' => null,
            'saldo_estoque_id' => SaldoEstoque::factory(),
            'quantidade_solicitada' => fake()->randomFloat(3, 1, 100),
            'justificativa' => fake()->sentence(),
            'status' => StatusRequisicaoMaterial::Aberta,
            'motivo_recusa' => null,
            'movimentacao_estoque_id' => null,
            'atendida_em' => null,
            'recusada_em' => null,
        ];
    }

    public function aberta(): static
    {
        return $this->state(['status' => StatusRequisicaoMaterial::Aberta]);
    }

    public function atendida(): static
    {
        return $this->state([
            'status' => StatusRequisicaoMaterial::Atendida,
            'atendida_em' => now(),
        ]);
    }

    public function recusada(): static
    {
        return $this->state([
            'status' => StatusRequisicaoMaterial::Recusada,
            'motivo_recusa' => fake()->sentence(),
            'recusada_em' => now(),
        ]);
    }
}
