<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoPorEmailRecebida;
use App\Mail\SolicitacaoCotacao;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\Fornecedor;
use App\Models\User;
use App\Services\CotacaoLinkService;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Resposta de cotação por e-mail sob dois tenants.
 *
 * A caixa IMAP é única da INSTALAÇÃO: as respostas de todas as empresas caem no
 * mesmo lugar. Desde a decisão 11 a resposta por e-mail só gera AVISO (nada é
 * gravado) — o que resta garantir é que uma empresa não interfira no aviso da outra
 * e que o assunto não exponha identificadores da instalação.
 */
beforeEach(function () {
    Mail::fake();
    config(['mail.imap.authserv_id' => 'mx.helix.test']);

    $this->tenantA = Tenant::create(['slug' => 'alfa-imap', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-imap', 'name' => 'Bravo', 'status' => 'active']);

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        $tenant->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    }

    TenantContext::forget();
});

/** Header `Authentication-Results` do nosso MX aprovando SPF+DKIM+DMARC do domínio. */
function autenticadoPor(string $dominio): string
{
    return "mx.helix.test; spf=pass smtp.mailfrom=forn@{$dominio}; dkim=pass header.d={$dominio}; dmarc=pass header.from={$dominio}";
}

/**
 * Cotação aguardando resposta, com link emitido, no tenant informado.
 *
 * @return array{0: Cotacao, 1: string} [cotação, referência pública do link]
 */
function cotacaoNoTenant(string $tenantId, string $emailFornecedor, string $emailCompradora): array
{
    return TenantContext::runFor($tenantId, function () use ($emailFornecedor, $emailCompradora, $tenantId) {
        $fornecedor = Fornecedor::factory()->create(['contato_email' => $emailFornecedor]);
        $compradora = User::factory()->create(['tenant_id' => $tenantId, 'email' => $emailCompradora]);

        $cotacao = Cotacao::factory()->create([
            'fornecedor_id' => $fornecedor->id,
            'criada_por' => $compradora->id,
            'valor' => null,
        ]);

        $emitido = app(CotacaoLinkService::class)->emitir($cotacao, now()->addDays(5));

        return [$cotacao, $emitido['link']->referencia];
    });
}

it('não suprime o aviso de um tenant porque o Message-ID já avisou outro', function () {
    $messageId = '<colisao-42@servidor-do-fornecedor>';

    [$cotA, $refA] = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test', 'comp@alfa.test');
    [$cotB, $refB] = cotacaoNoTenant($this->tenantB->id, 'forn@bravo.test', 'comp@bravo.test');

    $acao = app(ProcessarRespostaCotacaoAction::class);

    $acao->execute(new MensagemEmail(
        id: 'uid-a', messageId: $messageId, de: 'forn@alfa.test',
        assunto: "Re: cotação [COT-{$refA}]", corpo: 'Valor: R$ 100,00',
        autenticacao: autenticadoPor('alfa.test'),
    ));

    $res = $acao->execute(new MensagemEmail(
        id: 'uid-b', messageId: $messageId, de: 'forn@bravo.test',
        assunto: "Re: cotação [COT-{$refB}]", corpo: 'Valor: R$ 200,00',
        autenticacao: autenticadoPor('bravo.test'),
    ));

    expect($res?->id)->toBe($cotB->id);
    Mail::assertSent(RespostaCotacaoPorEmailRecebida::class, fn ($m) => $m->hasTo('comp@alfa.test'));
    Mail::assertSent(RespostaCotacaoPorEmailRecebida::class, fn ($m) => $m->hasTo('comp@bravo.test'));
});

it('a referência de uma cotação só avisa a compradora do tenant dela', function () {
    [$cotA, $refA] = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test', 'comp@alfa.test');
    cotacaoNoTenant($this->tenantB->id, 'forn@bravo.test', 'comp@bravo.test');

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-x', messageId: '<x@fornecedor>', de: 'forn@bravo.test',
        assunto: "Re: cotação [COT-{$refA}]", corpo: 'Valor: R$ 999,00',
        autenticacao: autenticadoPor('bravo.test'),
    ));

    expect($res?->id)->toBe($cotA->id);
    Mail::assertSent(RespostaCotacaoPorEmailRecebida::class, fn ($m) => $m->hasTo('comp@alfa.test') && ! $m->remetenteConfere);
    Mail::assertNotSent(RespostaCotacaoPorEmailRecebida::class, fn ($m) => $m->hasTo('comp@bravo.test'));
});

it('o assunto leva a referência pública do link, não a PK nem o token', function () {
    [$cot] = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test', 'comp@alfa.test');

    [$assunto, $link, $url] = TenantContext::runFor($this->tenantA->id, function () use ($cot) {
        $emitido = app(CotacaoLinkService::class)->emitir($cot, now()->addDays(3));
        $mail = new SolicitacaoCotacao($cot, $emitido['url'], $emitido['link']->referencia, $emitido['link']->expires_at);

        return [$mail->envelope()->subject, $emitido['link'], $emitido['url']];
    });

    $token = basename((string) parse_url($url, PHP_URL_PATH));

    expect($assunto)->toContain("[COT-{$link->referencia}]")
        ->and($assunto)->not->toContain("[COT-{$cot->id}]")
        ->and($assunto)->not->toContain($token)
        ->and(CotacaoLink::withoutTenantScope()->where('referencia', $link->referencia)->value('token_hash'))->not->toBe($link->referencia);
});

it('a migration do índice por tenant é reversível e idempotente', function () {
    $migration = require database_path('migrations/2026_09_15_000004_cotacoes_email_por_tenant_e_token_opaco.php');

    expect(Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq'))->toBeTrue();

    $migration->down();
    expect(Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq'))->toBeFalse()
        ->and(Schema::hasIndex('cotacoes', 'cotacoes_email_externo_id_unique'))->toBeTrue()
        ->and(Schema::hasColumn('cotacoes', 'email_token'))->toBeFalse();

    $migration->up();
    $migration->up();

    expect(Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq'))->toBeTrue()
        ->and(Schema::hasIndex('cotacoes', 'cotacoes_email_externo_id_unique'))->toBeFalse()
        ->and(Schema::hasColumn('cotacoes', 'email_token'))->toBeTrue();
});

it('a PK numérica [COT-{id}] (sequencial e global) não casa', function () {
    [$cotA] = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test', 'comp@alfa.test');

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-legado', messageId: '<legado@fornecedor>', de: 'forn@alfa.test',
        assunto: "Re: Solicitação de cotação [COT-{$cotA->id}]", corpo: 'Valor: R$ 50,00',
        autenticacao: autenticadoPor('alfa.test'),
    ));

    expect($res)->toBeNull();
    Mail::assertNothingSent();
});

it('a migration do link assinado é idempotente e reversível', function () {
    $migration = require database_path('migrations/2026_09_21_000001_create_cotacao_links_table.php');

    $migration->up(); // já migrada: não recria nem quebra
    expect(Schema::hasTable('cotacao_links'))->toBeTrue();

    $migration->down();
    expect(Schema::hasTable('cotacao_links'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('cotacao_links'))->toBeTrue();
});
