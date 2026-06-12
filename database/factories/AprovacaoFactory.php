<?php

namespace Database\Factories;

use App\Enums\NivelAlcada;
use App\Enums\StatusAprovacao;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aprovacao>
 */
class AprovacaoFactory extends Factory
{
    protected $model = Aprovacao::class;

    public function definition(): array
    {
        $requisicao = Requisicao::withoutGlobalScopes()->first() ?? Requisicao::factory()->create();

        return [
            'requisicao_id' => $requisicao->id,
            'etapa_alcada_id' => null,
            'ciclo' => 1,
            'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Gestor,
            'obrigatoria_emergencial' => false,
            'status' => StatusAprovacao::Pendente,
            'aprovador_id' => null,
            'justificativa' => null,
            'decidida_em' => null,
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn () => [
            'status' => StatusAprovacao::Pendente,
            'aprovador_id' => null,
            'justificativa' => null,
            'decidida_em' => null,
        ]);
    }

    public function aprovada(): static
    {
        return $this->state(fn () => [
            'status' => StatusAprovacao::Aprovada,
            'decidida_em' => now(),
        ]);
    }

    public function reprovada(): static
    {
        return $this->state(fn () => [
            'status' => StatusAprovacao::Reprovada,
            'justificativa' => fake()->sentence(),
            'decidida_em' => now(),
        ]);
    }
}
