<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Cria os usuários iniciais do sistema com diferentes perfis.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin Sistema',
            'email' => 'admin@comendador.com.br',
            'password' => Hash::make('senha@123'),
        ]);

        User::factory()->compradora()->create([
            'name' => 'Compradora Sênior',
            'email' => 'compradora@comendador.com.br',
            'password' => Hash::make('senha@123'),
        ]);

        // Diretor que participará de 2 unidades (vínculo criado no UnidadeSeeder)
        User::factory()->create([
            'name' => 'Diretor Regional',
            'email' => 'diretor@comendador.com.br',
            'password' => Hash::make('senha@123'),
        ]);

        User::factory()->create([
            'name' => 'Gestor da Obra',
            'email' => 'gestor@comendador.com.br',
            'password' => Hash::make('senha@123'),
        ]);

        User::factory()->create([
            'name' => 'Solicitante',
            'email' => 'solicitante@comendador.com.br',
            'password' => Hash::make('senha@123'),
        ]);

        User::factory()->create([
            'name' => 'Almoxarife',
            'email' => 'almoxarife@comendador.com.br',
            'password' => Hash::make('senha@123'),
        ]);
    }
}
