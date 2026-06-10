<?php

namespace Database\Factories;

use App\Enums\NivelAlcada;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapaAlcada>
 */
class EtapaAlcadaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faixa_alcada_id' => FaixaAlcada::factory(),
            'ordem' => 1,
            'nivel_exigido' => fake()->randomElement(NivelAlcada::cases())->value,
        ];
    }
}
