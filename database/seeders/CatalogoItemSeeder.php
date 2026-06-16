<?php

namespace Database\Seeders;

use App\Models\CatalogoItem;
use Illuminate\Database\Seeder;

class CatalogoItemSeeder extends Seeder
{
    /**
     * Popula o catálogo de itens com exemplos plausíveis para compras.
     */
    public function run(): void
    {
        $itens = [
            ['codigo' => 'ESC-001', 'descricao' => 'Papel A4 75g resma 500 folhas', 'unidade_medida' => 'resma', 'categoria' => 'material de escritório'],
            ['codigo' => 'ESC-002', 'descricao' => 'Caneta esferográfica azul', 'unidade_medida' => 'un', 'categoria' => 'material de escritório'],
            ['codigo' => 'ESC-003', 'descricao' => 'Grampeador de mesa', 'unidade_medida' => 'un', 'categoria' => 'material de escritório'],
            ['codigo' => 'ESC-004', 'descricao' => 'Toner para impressora HP 105A', 'unidade_medida' => 'un', 'categoria' => 'material de escritório'],
            ['codigo' => 'ESC-005', 'descricao' => 'Pasta suspensa kraft', 'unidade_medida' => 'un', 'categoria' => 'material de escritório'],
            ['codigo' => 'EPI-001', 'descricao' => 'Luva de raspa de couro', 'unidade_medida' => 'par', 'categoria' => 'epi'],
            ['codigo' => 'EPI-002', 'descricao' => 'Capacete de segurança classe B', 'unidade_medida' => 'un', 'categoria' => 'epi'],
            ['codigo' => 'EPI-003', 'descricao' => 'Óculos de proteção incolor', 'unidade_medida' => 'un', 'categoria' => 'epi'],
            ['codigo' => 'EPI-004', 'descricao' => 'Protetor auricular tipo plug', 'unidade_medida' => 'par', 'categoria' => 'epi'],
            ['codigo' => 'EPI-005', 'descricao' => 'Botina de segurança com bico de aço', 'unidade_medida' => 'par', 'categoria' => 'epi'],
            ['codigo' => 'FER-001', 'descricao' => 'Furadeira de impacto 1/2 polegada', 'unidade_medida' => 'un', 'categoria' => 'ferramentas'],
            ['codigo' => 'FER-002', 'descricao' => 'Jogo de chaves de fenda', 'unidade_medida' => 'jg', 'categoria' => 'ferramentas'],
            ['codigo' => 'FER-003', 'descricao' => 'Trena métrica 5 metros', 'unidade_medida' => 'un', 'categoria' => 'ferramentas'],
            ['codigo' => 'FER-004', 'descricao' => 'Marreta de borracha', 'unidade_medida' => 'un', 'categoria' => 'ferramentas'],
            ['codigo' => 'LIM-001', 'descricao' => 'Detergente neutro 5 litros', 'unidade_medida' => 'galão', 'categoria' => 'limpeza'],
            ['codigo' => 'LIM-002', 'descricao' => 'Papel toalha interfolhado', 'unidade_medida' => 'pct', 'categoria' => 'limpeza'],
            ['codigo' => 'LIM-003', 'descricao' => 'Álcool 70% 1 litro', 'unidade_medida' => 'un', 'categoria' => 'limpeza'],
            ['codigo' => 'LIM-004', 'descricao' => 'Vassoura de pelo sintético', 'unidade_medida' => 'un', 'categoria' => 'limpeza'],
            ['codigo' => 'CON-001', 'descricao' => 'Cimento Portland CP-II 50kg', 'unidade_medida' => 'saco', 'categoria' => 'construção'],
            ['codigo' => 'CON-002', 'descricao' => 'Tinta acrílica branca 18 litros', 'unidade_medida' => 'lata', 'categoria' => 'construção'],
        ];

        foreach ($itens as $item) {
            CatalogoItem::firstOrCreate(
                ['codigo' => $item['codigo']],
                [
                    'descricao' => $item['descricao'],
                    'unidade_medida' => $item['unidade_medida'],
                    'categoria' => $item['categoria'],
                    'ativo' => true,
                ]
            );
        }
    }
}
