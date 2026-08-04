<?php

namespace Database\Factories;

use App\Enums\StatusUnidade;
use App\Enums\TipoUnidade;
use App\Models\Unidade;
use Helix\Foundation\Models\Platform\Identity\Tenant;
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
            'tenant_id' => $this->resolveTenantId(),
            'nome' => fake()->company(),
            'tipo' => fake()->randomElement(TipoUnidade::cases())->value,
            'cnpj' => null,
            'endereco' => fake()->address(),
            'gestor_id' => null,
            'status' => StatusUnidade::Ativa->value,
        ];
    }

    /** Reusa o tenant existente (em teste = o primeiro criado) ou cria um. */
    private function resolveTenantId(): string
    {
        return Tenant::query()->orderBy('created_at')->value('id')
            ?? Tenant::create(['slug' => 'comendador', 'name' => 'Comendador', 'status' => 'active'])->id;
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
