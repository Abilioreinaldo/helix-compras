<?php

namespace Database\Factories;

use App\Enums\StatusUnidade;
use App\Enums\TipoUnidade;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unidade>
 */
class UnidadeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'tipo' => fake()->randomElement(TipoUnidade::cases())->value,
            'cnpj' => null,
            'endereco' => fake()->address(),
            'gestor_id' => null,
            'status' => StatusUnidade::Ativa->value,
        ];
    }

    /**
     * Define o tipo da unidade como obra.
     */
    public function obra(): static
    {
        return $this->state(['tipo' => TipoUnidade::Obra->value]);
    }

    /**
     * Define o tipo da unidade como posto.
     */
    public function posto(): static
    {
        return $this->state(['tipo' => TipoUnidade::Posto->value]);
    }

    /**
     * Define o tipo da unidade como central.
     */
    public function central(): static
    {
        return $this->state(['tipo' => TipoUnidade::Central->value]);
    }

    /**
     * Define a unidade como inativa.
     */
    public function inativa(): static
    {
        return $this->state(['status' => StatusUnidade::Inativa->value]);
    }
}
