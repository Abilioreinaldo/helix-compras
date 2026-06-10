<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * A senha atual usada pela factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'is_compradora' => false,
            'status' => 'ativo',
            'precisa_trocar_senha' => false,
        ];
    }

    /**
     * Indica que o e-mail do usuário não foi verificado.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuário administrador do sistema.
     */
    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }

    /**
     * Usuário compradora sênior.
     */
    public function compradora(): static
    {
        return $this->state(['is_compradora' => true]);
    }

    /**
     * Usuário que precisa trocar a senha no próximo login.
     */
    public function precisaTrocarSenha(): static
    {
        return $this->state(['precisa_trocar_senha' => true]);
    }
}
