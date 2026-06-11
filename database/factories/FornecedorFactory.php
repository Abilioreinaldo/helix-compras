<?php

namespace Database\Factories;

use App\Models\Fornecedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fornecedor>
 */
class FornecedorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razao_social' => fake()->company().' Ltda',
            'nome_fantasia' => fake()->boolean(70) ? fake()->company() : null,
            'cnpj' => fake()->numerify('##############'),
            'categoria' => fake()->randomElement(['materiais', 'servicos', 'equipamentos', 'combustivel', null]),
            'contato_nome' => fake()->name(),
            'contato_email' => fake()->safeEmail(),
            'contato_telefone' => fake()->numerify('(##) #####-####'),
            'homologado' => false,
            'homologado_em' => null,
            'homologado_por' => null,
            'ativo' => true,
            'observacoes' => null,
        ];
    }

    /**
     * Fornecedor já homologado.
     */
    public function homologado(): static
    {
        return $this->state([
            'homologado' => true,
            'homologado_em' => now(),
            'homologado_por' => null,
        ]);
    }

    /**
     * Fornecedor inativo.
     */
    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
