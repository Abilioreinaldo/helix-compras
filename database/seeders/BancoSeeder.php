<?php

namespace Database\Seeders;

use App\Models\Banco;
use Illuminate\Database\Seeder;

class BancoSeeder extends Seeder
{
    public function run(): void
    {
        $bancos = [
            ['nome' => 'Banco do Brasil', 'codigo_banco' => '001'],
            ['nome' => 'Bradesco', 'codigo_banco' => '237'],
            ['nome' => 'Itaú Unibanco', 'codigo_banco' => '341'],
            ['nome' => 'Santander', 'codigo_banco' => '033'],
            ['nome' => 'Caixa Econômica Federal', 'codigo_banco' => '104'],
        ];

        foreach ($bancos as $banco) {
            Banco::firstOrCreate(['codigo_banco' => $banco['codigo_banco']], $banco + ['ativo' => true]);
        }
    }
}
