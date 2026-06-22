<?php

namespace Database\Factories;

use App\Models\ItemReconciliacao;
use App\Models\ReconciliacaoBancaria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemReconciliacao>
 */
class ItemReconciliacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reconciliacao_bancaria_id' => ReconciliacaoBancaria::factory(),
            'numero_documento' => (string) fake()->unique()->numerify('DOC#####'),
            'valor' => fake()->randomFloat(2, 50, 5000),
            'data_transacao' => now()->toDateString(),
            'descricao' => fake()->sentence(3),
            'pagamento_id' => null,
            'status' => 'pendente_match',
        ];
    }
}
