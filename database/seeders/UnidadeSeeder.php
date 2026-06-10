<?php

namespace Database\Seeders;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\TipoUnidade;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use App\Models\Obra;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;

class UnidadeSeeder extends Seeder
{
    /**
     * Cria unidades, obras, vínculos de usuários e alçadas padrão.
     */
    public function run(): void
    {
        $gestor = User::where('email', 'gestor@comendador.com.br')->firstOrFail();
        $diretor = User::where('email', 'diretor@comendador.com.br')->firstOrFail();
        $solicitante = User::where('email', 'solicitante@comendador.com.br')->firstOrFail();
        $almoxarife = User::where('email', 'almoxarife@comendador.com.br')->firstOrFail();

        // --- Unidade 1: Obra ---
        $unidadeObra = Unidade::withoutGlobalScopes()->create([
            'nome' => 'Obra Expansão Norte',
            'tipo' => TipoUnidade::Obra->value,
            'cnpj' => '12345678000195',
            'endereco' => 'Rodovia BR-101, km 45, Norte',
            'gestor_id' => $gestor->id,
            'status' => 'ativa',
        ]);

        Obra::create([
            'unidade_id' => $unidadeObra->id,
            'iniciada_em' => '2024-01-15',
            'previsao_termino' => '2026-12-31',
            'status' => 'ativa',
            'verba' => 2500000.00,
        ]);

        // --- Unidade 2: Posto ---
        $unidadePosto = Unidade::withoutGlobalScopes()->create([
            'nome' => 'Posto Comendador Centro',
            'tipo' => TipoUnidade::Posto->value,
            'cnpj' => '98765432000110',
            'endereco' => 'Av. Central, 1000, Centro',
            'gestor_id' => $gestor->id,
            'status' => 'ativa',
        ]);

        // --- Unidade 3: Central ---
        $unidadeCentral = Unidade::withoutGlobalScopes()->create([
            'nome' => 'Central Administrativa',
            'tipo' => TipoUnidade::Central->value,
            'cnpj' => null,
            'endereco' => 'Rua das Empresas, 500, Bairro Empresarial',
            'gestor_id' => null,
            'status' => 'ativa',
        ]);

        // --- Vínculos de usuários ---
        // Gestor vinculado à obra
        $unidadeObra->usuarios()->attach($gestor->id, [
            'perfil' => Perfil::Aprovador->value,
            'nivel_alcada' => NivelAlcada::Gestor->value,
        ]);

        // Diretor vinculado à obra e ao posto
        $unidadeObra->usuarios()->attach($diretor->id, [
            'perfil' => Perfil::Aprovador->value,
            'nivel_alcada' => NivelAlcada::Diretor->value,
        ]);
        $unidadePosto->usuarios()->attach($diretor->id, [
            'perfil' => Perfil::Aprovador->value,
            'nivel_alcada' => NivelAlcada::Diretor->value,
        ]);

        // Solicitante na obra
        $unidadeObra->usuarios()->attach($solicitante->id, [
            'perfil' => Perfil::Solicitante->value,
            'nivel_alcada' => null,
        ]);

        // Almoxarife no posto
        $unidadePosto->usuarios()->attach($almoxarife->id, [
            'perfil' => Perfil::Almoxarife->value,
            'nivel_alcada' => null,
        ]);

        // --- Alçadas padrão ---
        // Faixa 1: até R$5.000 → apenas Gestor
        $faixaGestor = FaixaAlcada::create([
            'nome' => 'Pequenas Compras (até R$5.000)',
            'valor_minimo' => 0.00,
            'valor_maximo' => 5000.00,
            'is_emergencial' => false,
            'ativo' => true,
        ]);
        EtapaAlcada::create([
            'faixa_alcada_id' => $faixaGestor->id,
            'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Gestor->value,
        ]);

        // Faixa 2: R$5.000 a R$20.000 → apenas Diretor
        $faixaDiretor = FaixaAlcada::create([
            'nome' => 'Compras Médias (R$5.001 a R$20.000)',
            'valor_minimo' => 5000.01,
            'valor_maximo' => 20000.00,
            'is_emergencial' => false,
            'ativo' => true,
        ]);
        EtapaAlcada::create([
            'faixa_alcada_id' => $faixaDiretor->id,
            'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Diretor->value,
        ]);

        // Faixa 3: acima de R$20.000 → Diretor + CEO
        $faixaAlta = FaixaAlcada::create([
            'nome' => 'Grandes Compras (acima R$20.000)',
            'valor_minimo' => 20000.01,
            'valor_maximo' => null,
            'is_emergencial' => false,
            'ativo' => true,
        ]);
        EtapaAlcada::create([
            'faixa_alcada_id' => $faixaAlta->id,
            'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Diretor->value,
        ]);
        EtapaAlcada::create([
            'faixa_alcada_id' => $faixaAlta->id,
            'ordem' => 2,
            'nivel_exigido' => NivelAlcada::Ceo->value,
        ]);

        // Faixa 4: Emergencial → Diretor
        $faixaEmergencial = FaixaAlcada::create([
            'nome' => 'Emergencial',
            'valor_minimo' => 0.00,
            'valor_maximo' => null,
            'is_emergencial' => true,
            'ativo' => true,
        ]);
        EtapaAlcada::create([
            'faixa_alcada_id' => $faixaEmergencial->id,
            'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Diretor->value,
        ]);
    }
}
