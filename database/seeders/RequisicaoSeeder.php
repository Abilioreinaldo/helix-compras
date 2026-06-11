<?php

namespace Database\Seeders;

use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;

class RequisicaoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('is_admin', true)->first();
        $unidade = Unidade::withoutGlobalScopes()->first();
        $centro = CentroCusto::withoutGlobalScopes()->first();

        if (! $admin || ! $unidade || ! $centro) {
            return;
        }

        // Rascunho
        $rascunho = Requisicao::create([
            'solicitante_id' => $admin->id,
            'unidade_id' => $unidade->id,
            'centro_custo_id' => $centro->id,
            'obra_id' => null,
            'status' => StatusRequisicao::Rascunho,
            'urgente' => false,
            'is_emergencial' => false,
        ]);

        $rascunho->itens()->create([
            'descricao' => 'Papel A4 resma',
            'quantidade' => 10,
            'unidade_medida' => 'un',
            'valor_unitario_estimado' => 25.90,
        ]);

        // Submetida
        $faixa = FaixaAlcada::first();
        $submetida = Requisicao::create([
            'solicitante_id' => $admin->id,
            'unidade_id' => $unidade->id,
            'centro_custo_id' => $centro->id,
            'obra_id' => null,
            'status' => StatusRequisicao::AguardandoTriagem,
            'codigo' => 'REQ-'.now()->year.'-000001',
            'faixa_alcada_id' => $faixa?->id,
            'submetida_em' => now()->subMinutes(30),
            'urgente' => true,
            'is_emergencial' => false,
        ]);

        $submetida->itens()->create([
            'descricao' => 'Caneta esferográfica azul',
            'quantidade' => 50,
            'unidade_medida' => 'un',
            'valor_unitario_estimado' => 1.50,
        ]);

        $submetida->logs()->create([
            'status_anterior' => StatusRequisicao::Rascunho->value,
            'status_novo' => StatusRequisicao::AguardandoTriagem->value,
            'user_id' => $admin->id,
            'automatico' => false,
        ]);
    }
}
