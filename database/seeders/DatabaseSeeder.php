<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsuarioSeeder::class,
            UnidadeSeeder::class,
            CatalogoItemSeeder::class,
            FornecedorSeeder::class,
            BancoSeeder::class,
            CentroCustoSeeder::class,
            RequisicaoSeeder::class,
            CargaMediaSeeder::class,
        ]);
    }
}
