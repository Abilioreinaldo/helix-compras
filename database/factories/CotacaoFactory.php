<?php

namespace Database\Factories;

use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cotacao>
 */
class CotacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requisicao = Requisicao::withoutGlobalScopes()->first() ?? Requisicao::factory()->create();
        $fornecedor = Fornecedor::first() ?? Fornecedor::factory()->homologado()->create();
        $criador = User::first() ?? User::factory()->create();

        return [
            'requisicao_id' => $requisicao->id,
            'fornecedor_id' => $fornecedor->id,
            'valor' => fake()->randomFloat(2, 100, 50000),
            'prazo_entrega_dias' => fake()->optional()->numberBetween(1, 30),
            'arquivo_path' => null,
            'arquivo_nome_original' => null,
            'observacoes' => null,
            'vencedora' => false,
            'criada_por' => $criador->id,
            'vencedora_definida_em' => null,
            'vencedora_definida_por' => null,
            'cancelada_em' => null,
            'motivo_cancelamento' => null,
        ];
    }

    public function vencedora(): static
    {
        return $this->state([
            'vencedora' => true,
            'vencedora_definida_em' => now(),
        ]);
    }
}
