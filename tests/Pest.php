<?php

use App\Models\User;
use Helix\Foundation\Contracts\Channels\CommercialMailer;
use Helix\Foundation\Contracts\Channels\DnsResolver;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Channels\TenantChannelService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Helix\Foundation\Support\StepUpProof;
use Helix\Foundation\Testing\Channels\FakeDnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use Tests\Support\EntregaComercialDeTeste;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Contexto de tenant nos testes: em produção todo write nasce num request
    // autenticado (tenant no contexto). Muitos testes deste app são pré-tenant e
    // criam dados ANTES do actingAs — sem contexto, o BelongsToTenant carimbaria
    // tenant_id null e o próprio teste não veria o dado. Fixamos o tenant canônico
    // (o mesmo que a UserFactory reusa) para o setup carimbar corretamente; testes
    // multi-tenant fazem opt-out com TenantContext::forget() e semeiam tenant_id
    // explícito (padrão Fuel).
    ->beforeEach(function () {
        $tenant = Tenant::query()->orderBy('created_at')->first()
            ?? Tenant::create(['slug' => 'comendador', 'name' => 'Comendador', 'status' => 'active']);

        // v0.3.0: tenant_id saiu do $fillable do entitlement — cria pela relação.
        $tenant->features()->firstOrCreate(
            ['feature' => 'compras'],
            ['enabled' => true],
        );

        TenantContext::set($tenant->id);
    })
    ->afterEach(fn () => TenantContext::forget())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Canal COMERCIAL de e-mail ATIVO do tenant (fundação v0.7.0, decisão 13).
 *
 * A solicitação de cotação ao fornecedor fala em nome do CLIENTE e sai pelo domínio de
 * envio DELE (`CommercialMessenger`). Sem canal ativo, o envio falha FECHADO — não há
 * queda para o canal da suíte. Todo teste que exercita o envio precisa, portanto, montar
 * o canal antes; é o mesmo caminho da tela `/admin/canais` (configurar → verificar →
 * ativar), com os fakes de DNS e de entrega que o próprio pacote publica.
 *
 * Devolve o FakeCommercialMailer: é nele que o teste lê o remetente e o host usados — a
 * prova de que o e-mail saiu pelo domínio do cliente, e não pelo da plataforma.
 */
function canalDeEmailAtivo(string $tenantId, ?User $admin = null, string $dominio = 'envio.cliente.test'): EntregaComercialDeTeste
{
    $dns = new FakeDnsResolver;
    $mailer = new EntregaComercialDeTeste;

    app()->instance(DnsResolver::class, $dns);
    app()->instance(CommercialMailer::class, $mailer);

    $config = [
        'domain' => $dominio,
        'from_address' => 'compras@'.$dominio,
        'from_name' => 'Compras do Cliente',
        'host' => 'smtp.provedor.test',
        'port' => 587,
        'encryption' => 'tls',
        'spf_include' => 'spf.provedor.test',
        'dkim_selector' => 'helix1',
        'username' => 'cliente',
        'password' => 'senha-smtp-do-cliente',
    ];

    $dns->publicarTudo($config['domain'], $config['spf_include'], $config['dkim_selector']);

    // A allowlist de hosts SMTP é da PLATAFORMA (o cliente traz só o domínio e as
    // credenciais dele) e é FAIL-CLOSED: vazia = nenhum provedor aceito. Na suíte ela
    // fica vazia por padrão — de propósito —, então o cenário declara o host do fake.
    config(['foundation.channels.email.smtp_allowed_hosts' => [$config['host']]]);

    TenantContext::runFor($tenantId, function () use ($tenantId, $admin, $config) {
        // `channels.manage` é ADMIN_ONLY no catálogo: quem monta o canal é o admin da
        // empresa. A senha da UserFactory é 'password' — é ela que fecha o step-up
        // (sem 2FA obrigatório na suíte, senha basta).
        $admin ??= User::factory()->admin()->create(['tenant_id' => $tenantId]);

        $canais = app(TenantChannelService::class);
        $credencial = $canais->configure('email', 'smtp', $config, $admin, new StepUpProof('password'));
        $canais->verify($credencial, $admin, new StepUpProof('password'));
        $canais->activate($credencial, $admin, new StepUpProof('password'));
    });

    return $mailer;
}

/**
 * Harness do índice UNIQUE de catálogo (`saldos_estoque_tenant_catalogo_uq`) para os testes
 * de fusão/saneamento (FaseV11A/B), que simulam o estado legado dropando/recriando o índice
 * NO MEIO do teste.
 *
 * Esse padrão só isola em SQLite: no MySQL o DDL faz commit implícito e fura o
 * RefreshDatabase (vira espera de lock/hang). Por isso estes casos são **SQLite-only** — a
 * semântica do UNIQUE de catálogo em MySQL é coberta pelo `SaldoCatalogoUnicoTest` (portável,
 * sem DDL no meio do teste). Chamado num driver != sqlite, o harness PULA o teste.
 */
function harnessDropIndiceCatalogoSaldos(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        Assert::markTestSkipped('Muta índice no meio do teste — SQLite-only (A2 em MySQL: ver SaldoCatalogoUnicoTest).');
    }

    // O índice ganhou `tenant_id` na 2ª auditoria adversarial (migration 2026_09_17_000004)
    // e mudou de nome; o nome antigo fica no drop para o harness seguir servindo a uma base
    // que ainda não rodou aquela migration.
    DB::statement('DROP INDEX IF EXISTS saldos_estoque_tenant_catalogo_uq');
    DB::statement('DROP INDEX IF EXISTS saldos_estoque_catalogo_unique');
}

/**
 * Recria o índice UNIQUE de catálogo (pós-saneamento) — par do
 * {@see harnessDropIndiceCatalogoSaldos()}. Mesma regra: SQLite-only.
 */
function harnessCriaIndiceCatalogoSaldos(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        Assert::markTestSkipped('Muta índice no meio do teste — SQLite-only.');
    }

    DB::statement(
        'CREATE UNIQUE INDEX saldos_estoque_tenant_catalogo_uq ON saldos_estoque '
        .'(tenant_id, unidade_id, deposito, item_catalogo_id) '
        .'WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL'
    );
}
