<?php

namespace Database\Factories;

use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\Obra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisicao>
 */
class RequisicaoFactory extends Factory
{
    protected $model = Requisicao::class;

    public function definition(): array
    {
        $unidade = Unidade::withoutGlobalScopes()->first() ?? Unidade::factory()->create();
        $solicitante = User::first() ?? User::factory()->create();
        $centroCusto = CentroCusto::withoutGlobalScopes()->first()
            ?? CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

        return [
            'solicitante_id' => $solicitante->id,
            'unidade_id' => $unidade->id,
            'centro_custo_id' => $centroCusto->id,
            'obra_id' => null,
            'status' => StatusRequisicao::Rascunho,
            'urgente' => false,
            'is_emergencial' => false,
            'justificativa' => null,
            'atrasada' => false,
            'faixa_alcada_id' => null,
            'escalada_verba' => false,
            'consumo_verba_no_submit' => null,
            'submetida_em' => null,
            'triagem_iniciada_em' => null,
            'cancelada_em' => null,
            'cancelada_por' => null,
            'motivo_cancelamento' => null,
            'codigo' => null,
        ];
    }

    public function aguardandoTriagem(): static
    {
        return $this->state(fn () => [
            'status' => StatusRequisicao::AguardandoTriagem,
            'codigo' => 'REQ-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'submetida_em' => now(),
        ]);
    }

    public function emTriagem(): static
    {
        return $this->state(fn () => [
            'status' => StatusRequisicao::EmTriagem,
            'codigo' => 'REQ-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'submetida_em' => now()->subHours(2),
            'triagem_iniciada_em' => now(),
        ]);
    }

    public function comObra(Obra $obra): static
    {
        return $this->state(fn () => ['obra_id' => $obra->id]);
    }
}
