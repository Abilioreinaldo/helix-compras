<?php

namespace Database\Factories;

use App\Models\CatalogoItem;
use App\Models\Fornecedor;
use App\Models\PrecoHomologado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrecoHomologado>
 */
class PrecoHomologadoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'item_catalogo_id' => CatalogoItem::factory(),
            'fornecedor_id' => Fornecedor::factory(),
            'preco' => fake()->randomFloat(2, 5, 500),
            'preferencial' => false,
            'validade_inicio' => now()->subDay()->toDateString(),
            'validade_fim' => now()->addDays(30)->toDateString(),
            'ativo' => true,
            'observacao' => null,
        ];
    }

    /** Homologação fora da validade (vencida). */
    public function vencido(): static
    {
        return $this->state([
            'validade_inicio' => now()->subDays(60)->toDateString(),
            'validade_fim' => now()->subDay()->toDateString(),
        ]);
    }

    /** Homologação preferencial (desempata quando há mais de um fornecedor). */
    public function preferencial(): static
    {
        return $this->state(['preferencial' => true]);
    }

    /** Homologação inativa. */
    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
