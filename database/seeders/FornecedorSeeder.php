<?php

namespace Database\Seeders;

use App\Models\Fornecedor;
use App\Models\User;
use Illuminate\Database\Seeder;

class FornecedorSeeder extends Seeder
{
    /**
     * Cria fornecedores de exemplo: 2 homologados e 1 pendente.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@comendador.com.br')->firstOrFail();

        Fornecedor::create([
            'razao_social' => 'Distribuidora Norte Ltda',
            'nome_fantasia' => 'DistriNorte',
            'cnpj' => '11222333000181',
            'categoria' => 'materiais',
            'contato_nome' => 'Carlos Silva',
            'contato_email' => 'carlos@distrinorte.com.br',
            'contato_telefone' => '(11) 98765-4321',
            'homologado' => true,
            'homologado_em' => now()->subDays(30),
            'homologado_por' => $admin->id,
            'ativo' => true,
        ]);

        Fornecedor::create([
            'razao_social' => 'Equipamentos Industriais do Brasil S.A.',
            'nome_fantasia' => 'EIB',
            'cnpj' => '44555666000177',
            'categoria' => 'equipamentos',
            'contato_nome' => 'Ana Souza',
            'contato_email' => 'ana@eib.com.br',
            'contato_telefone' => '(21) 3333-4444',
            'homologado' => true,
            'homologado_em' => now()->subDays(15),
            'homologado_por' => $admin->id,
            'ativo' => true,
        ]);

        Fornecedor::create([
            'razao_social' => 'Serviços Gerais Omega ME',
            'nome_fantasia' => null,
            'cnpj' => '77888999000165',
            'categoria' => 'servicos',
            'contato_nome' => 'João Mendes',
            'contato_email' => 'joao@omega.com.br',
            'contato_telefone' => '(31) 99999-0000',
            'homologado' => false,
            'homologado_em' => null,
            'homologado_por' => null,
            'ativo' => true,
        ]);
    }
}
