<?php

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Identity\UserService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Decisão 17 — FK composta unidade_user(user_id, tenant_id) → tenant_user(user_id, tenant_id)
| ON DELETE CASCADE (parecer DBA), e autoria validada por membership ATIVA na gravação.
*/

const MIGRATION_FK_UNIDADE_USER = '2026_09_21_000002_unidade_user_fk_composta_tenant_user.php';

function migrationFkUnidadeUser(): object
{
    return require database_path('migrations/'.MIGRATION_FK_UNIDADE_USER);
}

beforeEach(function () {
    $this->tenantA = TenantContext::id();
    $this->tenantB = Tenant::create(['slug' => 'bravo-vinculo', 'name' => 'Bravo', 'status' => 'active'])->id;
});

it('remover a membership remove o acesso às unidades daquele tenant (e só dele)', function () {
    $usuario = User::factory()->create();
    $unidadeA = Unidade::factory()->create();
    $usuario->unidades()->attach($unidadeA->id, ['perfil' => Perfil::Aprovador->value]);

    // O mesmo usuário também é membro do tenant B, com vínculo lá.
    $usuario->memberships()->syncWithoutDetaching([$this->tenantB => ['status' => 'active', 'is_admin' => false, 'access_scope' => 'corporate']]);
    $unidadeB = TenantContext::runFor($this->tenantB, fn () => Unidade::factory()->create());
    TenantContext::runFor($this->tenantB, fn () => $usuario->unidades()->attach($unidadeB->id, ['perfil' => Perfil::Aprovador->value]));

    expect($usuario->temPerfil(Perfil::Aprovador))->toBeTrue();

    app(UserService::class)->removeMembership($usuario, $this->tenantA);

    expect(DB::table('unidade_user')->where('user_id', $usuario->id)->where('tenant_id', $this->tenantA)->count())->toBe(0)
        ->and($usuario->fresh()->temPerfil(Perfil::Aprovador))->toBeFalse()
        ->and(DB::table('unidade_user')->where('user_id', $usuario->id)->where('tenant_id', $this->tenantB)->count())->toBe(1);
});

it('o banco recusa vínculo com unidade para quem não é membro do tenant', function () {
    $semMembership = User::factory()->create();
    DB::table('tenant_user')->where('user_id', $semMembership->id)->delete();
    $unidade = Unidade::factory()->create();

    expect(fn () => DB::table('unidade_user')->insert([
        'tenant_id' => $this->tenantA,
        'user_id' => $semMembership->id,
        'unidade_id' => $unidade->id,
        'perfil' => Perfil::Solicitante->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('a migration aborta com a query de diagnóstico diante de vínculo órfão, sem corrigir sozinha', function () {
    $migration = migrationFkUnidadeUser();
    $migration->down();

    $usuario = User::factory()->create();
    $unidade = Unidade::factory()->create();
    $usuario->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    DB::table('tenant_user')->where('user_id', $usuario->id)->delete(); // sem FK: o vínculo fica órfão

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'select uu.* from unidade_user uu where not exists');

    // Não corrigiu nada e não criou a FK.
    expect(DB::table('unidade_user')->where('user_id', $usuario->id)->count())->toBe(1)
        ->and(collect(Schema::getForeignKeys('unidade_user'))->contains(fn ($fk) => $fk['foreign_table'] === 'tenant_user'))->toBeFalse();
});

it('a migration aborta diante de vínculo cruzado (tenant do vínculo ≠ tenant da unidade)', function () {
    $migration = migrationFkUnidadeUser();
    $migration->down();
    // 4ª auditoria (COMPRAS-5): a FK composta (unidade_id, tenant_id) → unidades, criada
    // DEPOIS desta migration, hoje recusa o vínculo cruzado no insert. Para reproduzir o
    // estado LEGADO que esta migration tem de diagnosticar, ela também sai de cena aqui.
    (require database_path('migrations/2026_09_22_000001_fk_composta_cotacao_links_e_unidade_user_para_a_mae.php'))->down();

    $usuario = User::factory()->create();
    $usuario->memberships()->syncWithoutDetaching([$this->tenantB => ['status' => 'active', 'is_admin' => false, 'access_scope' => 'corporate']]);
    $unidadeA = Unidade::factory()->create();

    DB::table('unidade_user')->insert([
        'tenant_id' => $this->tenantB, 'user_id' => $usuario->id, 'unidade_id' => $unidadeA->id,
        'perfil' => Perfil::Solicitante->value, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'CRUZADO');
});

it('a migration é idempotente e reversível', function () {
    $migration = migrationFkUnidadeUser();
    $temFk = fn () => collect(Schema::getForeignKeys('unidade_user'))
        ->contains(fn ($fk) => $fk['columns'] === ['user_id', 'tenant_id'] && $fk['foreign_table'] === 'tenant_user');

    expect($temFk())->toBeTrue();
    $migration->up();
    expect($temFk())->toBeTrue();

    $migration->down();
    expect($temFk())->toBeFalse();
    $migration->down();

    $migration->up();
    expect($temFk())->toBeTrue();
});

// ─── Autoria: executor de comando precisa de identidade e membership ATIVAS ────

/** Uma saída de estoque em 05/2026 — base mínima para o rateio mensal ter o que ratear. */
function uum_consumoMaio(Unidade $unidade, User $registrador): void
{
    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id, 'deposito' => 'Depósito', 'descricao_item' => 'Consumo',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Consumo'), 'unidade_medida' => 'un',
        'quantidade' => 10.0, 'custo_medio_ponderado' => 100.0, 'valor_total' => 1000.0,
    ]);
    $mov = MovimentacaoEstoque::create([
        'saldo_estoque_id' => $saldo->id, 'tipo' => TipoMovimentacao::Saida, 'quantidade' => 1,
        'custo_unitario' => 100.0, 'valor_total' => 100.0, 'motivo' => 'consumo teste', 'registrado_por' => $registrador->id,
    ]);
    MovimentacaoEstoque::where('id', $mov->id)->update(['created_at' => Carbon::create(2026, 5, 15, 12)]);
}

it('rateio mensal só aceita executor Admin com identidade E membership ativas', function (string $caso, int $exit, int $rateios) {
    $admin = User::factory()->admin()->create();
    uum_consumoMaio(Unidade::factory()->create(), User::factory()->create());

    match ($caso) {
        'identidade suspensa' => DB::table('users')->where('id', $admin->id)->update(['status' => 'inactive']),
        'membership suspensa' => DB::table('tenant_user')->where('user_id', $admin->id)->update(['status' => 'suspended']),
        default => null, // controle: o mesmo cenário com tudo ativo executa
    };

    TenantContext::forget();

    $this->artisan('rateio:executar-mensal', [
        '--valor-central' => '1000', '--mes' => 5, '--ano' => 2026, '--executado-por' => $admin->id,
    ])->assertExitCode($exit);

    expect(DB::table('rateios_centrais')->count())->toBe($rateios);
})->with([
    'ativo (controle)' => ['ativo', 0, 1],
    'identidade suspensa' => ['identidade suspensa', 1, 0],
    'membership suspensa' => ['membership suspensa', 1, 0],
]);

it('saneamento de duplicatas recusa executor Admin com identidade suspensa', function () {
    $admin = User::factory()->admin()->create();
    DB::table('users')->where('id', $admin->id)->update(['status' => 'inactive']);
    TenantContext::forget();

    $this->artisan('estoque:sanear-duplicatas-catalogo', ['--dry-run' => true, '--executado-por' => $admin->id])
        ->assertExitCode(1);
});
