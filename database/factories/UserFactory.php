<?php

namespace Database\Factories;

use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Role;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Identity\EntitlementService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
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
            'tenant_id' => $this->resolveTenantId(),
            'is_admin' => false,
            'status' => 'active',
            'precisa_trocar_senha' => false,
        ];
    }

    /**
     * Resolve o tenant do usuário: reusa o tenant existente (em dev = o tenant da
     * suíte; em teste = o primeiro criado) ou cria um. Garante o entitlement
     * `compras` (o app enforça `feature:compras` — sem isto as rotas dariam 403).
     */
    private function resolveTenantId(): string
    {
        $tenant = Tenant::query()->orderBy('created_at')->first()
            ?? Tenant::create(['slug' => 'comendador', 'name' => 'Comendador', 'status' => 'active']);

        // v0.3.0: tenant_id saiu do $fillable do entitlement — cria pela relação.
        $tenant->features()->firstOrCreate(
            ['feature' => 'compras'],
            ['enabled' => true],
        );

        return $tenant->id;
    }

    /**
     * `tenant_id` e `is_admin` saíram do $fillable do User (estado de servidor, v0.2.1):
     * o caminho de produção é o forceCreate do UserService da fundação. A factory é
     * infraestrutura de teste/seed e também escreve como servidor — daí o forceFill,
     * que mantém `User::factory()->create(['tenant_id' => $x])` funcionando sem
     * reabrir o mass assignment no código da aplicação.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): User
    {
        return (new User)->forceFill($attributes);
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
     * Todo usuário criado ganha a membership no seu tenant home (como em produção,
     * via UserService::createUser) — com is_admin espelhando a flag. Sem isto o
     * EnsureTenantActive/AdminMiddleware (que leem o pivot) barrariam o acesso.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->memberships()->syncWithoutDetaching([
                $user->getAttributes()['tenant_id'] => [
                    'is_admin' => (bool) ($user->getAttributes()['is_admin'] ?? false),
                    'status' => 'active',
                ],
            ]);
        });
    }

    /**
     * Usuário administrador do tenant (pivot tenant_user.is_admin via configure()).
     */
    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }

    /**
     * Compradora sênior — papel RBAC `compras` (o ComprasUser::temPerfil usa hasRole).
     */
    public function compradora(): static
    {
        return $this->afterCreating(fn (User $user) => $this->atribuirPapel($user, 'compras', 'Compras'));
    }

    /**
     * Financeiro — papel RBAC `financeiro`.
     */
    public function financeiro(): static
    {
        return $this->afterCreating(fn (User $user) => $this->atribuirPapel($user, 'financeiro', 'Financeiro'));
    }

    /**
     * Usuário que precisa trocar a senha no próximo login.
     */
    public function precisaTrocarSenha(): static
    {
        return $this->state(['precisa_trocar_senha' => true]);
    }

    /** Semeia o RBAC canônico de Compras no tenant (papel + permissões padrão) e atribui o papel. */
    private function atribuirPapel(User $user, string $slug, string $name): void
    {
        $tenant = Tenant::findOrFail($user->getAttributes()['tenant_id']);
        app(EntitlementService::class)->seedRbac($tenant, 'compras');

        // Role usa BelongsToTenant: lê sob o tenant do usuário, não o do contexto.
        $role = TenantContext::runFor((string) $tenant->id, fn () => Role::where('tenant_id', $tenant->id)->where('slug', $slug)->firstOrFail());

        $user->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $tenant->id]]);
    }
}
