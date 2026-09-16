<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\MensagemEmail;
use App\Mail\SolicitacaoCotacao;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Captura IMAP de cotações sob dois tenants (auditoria adversarial, achado MÉDIO).
 *
 * A caixa IMAP é única da INSTALAÇÃO (limitação de infra, fora do código): as
 * respostas de todas as empresas caem no mesmo lugar. O que o app precisa garantir
 * é que uma empresa não interfira na outra por esse canal — e é isso que estes
 * testes provam falhar do lado do atacante.
 */
beforeEach(function () {
    Mail::fake();
    // authserv-id do NOSSO MX: só o carimbo dele conta (3ª auditoria adversarial).
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

/** Cria uma cotação aguardando resposta no tenant informado. */
function cotacaoNoTenant(string $tenantId, string $emailFornecedor): Cotacao
{
    return TenantContext::runFor($tenantId, function () use ($emailFornecedor) {
        $fornecedor = Fornecedor::factory()->create(['contato_email' => $emailFornecedor]);
        $compradora = User::factory()->create();

        return Cotacao::factory()->create([
            'fornecedor_id' => $fornecedor->id,
            'criada_por' => $compradora->id,
            'valor' => null,
        ]);
    });
}

it('não descarta a resposta de um tenant porque o Message-ID já existe em outro', function () {
    // Mesmo Message-ID em duas empresas: com o unique GLOBAL em email_externo_id,
    // a resposta do tenant B era engolida em silêncio (idempotência global).
    $messageId = '<colisao-42@servidor-do-fornecedor>';

    $cotA = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');
    $cotB = cotacaoNoTenant($this->tenantB->id, 'forn@bravo.test');

    $acao = app(ProcessarRespostaCotacaoAction::class);

    $acao->execute(new MensagemEmail(
        id: 'uid-a', messageId: $messageId, de: 'forn@alfa.test',
        assunto: "Re: cotação [COT-{$cotA->email_token}]", corpo: 'Valor: R$ 100,00',
        autenticacao: autenticadoPor('alfa.test'),
    ));

    $res = $acao->execute(new MensagemEmail(
        id: 'uid-b', messageId: $messageId, de: 'forn@bravo.test',
        assunto: "Re: cotação [COT-{$cotB->email_token}]", corpo: 'Valor: R$ 200,00',
        autenticacao: autenticadoPor('bravo.test'),
    ));

    expect($res)->not->toBeNull()
        ->and((float) $cotB->fresh()->valor_respondido)->toBe(200.00)
        ->and((float) $cotA->fresh()->valor_respondido)->toBe(100.00);
});

it('segue sem duplicar quando o mesmo Message-ID chega duas vezes no mesmo tenant', function () {
    $cot = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');

    $msg = new MensagemEmail(
        id: 'uid-1', messageId: '<repetido@fornecedor>', de: 'forn@alfa.test',
        assunto: "Re: cotação [COT-{$cot->email_token}]", corpo: 'Valor: R$ 100,00',
        autenticacao: autenticadoPor('alfa.test'),
    );

    app(ProcessarRespostaCotacaoAction::class)->execute($msg);
    $segunda = app(ProcessarRespostaCotacaoAction::class)->execute($msg);

    expect($segunda)->toBeNull()
        ->and(Cotacao::withoutTenantScope()->where('email_externo_id', $msg->messageId)->count())->toBe(1);
});

it('não casa a resposta pelo token de uma cotação de outro tenant', function () {
    // O token é opaco e único: usar o do tenant A só alcança a cotação do tenant A.
    $cotA = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');
    $cotB = cotacaoNoTenant($this->tenantB->id, 'forn@bravo.test');

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-x', messageId: '<x@fornecedor>', de: 'forn@bravo.test',
        assunto: "Re: cotação [COT-{$cotA->email_token}]", corpo: 'Valor: R$ 999,00',
        autenticacao: autenticadoPor('bravo.test'),
    ));

    // Remetente de B não confere com o fornecedor de A → recusado, e nada escrito.
    expect($res)->toBeNull()
        ->and($cotA->fresh()->resposta_recebida_em)->toBeNull()
        ->and($cotB->fresh()->resposta_recebida_em)->toBeNull();
});

it('não expõe a PK sequencial da instalação no assunto enviado ao fornecedor', function () {
    $cot = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');

    // O envelope lê a requisição da cotação — leitura escopada, como no envio real.
    $assunto = TenantContext::runFor($this->tenantA->id, function () use ($cot) {
        Mail::to('forn@alfa.test')->send(new SolicitacaoCotacao($cot));

        return (new SolicitacaoCotacao($cot))->envelope()->subject;
    });

    Mail::assertSent(SolicitacaoCotacao::class);
    expect($assunto)->toContain("[COT-{$cot->email_token}]")
        ->and($assunto)->not->toContain("[COT-{$cot->id}]");

    expect($cot->email_token)->not->toBeNull()
        ->and($cot->email_token)->not->toBe((string) $cot->id);
});

it('a migration do índice por tenant é reversível e idempotente', function () {
    $migration = require database_path('migrations/2026_09_15_000004_cotacoes_email_por_tenant_e_token_opaco.php');

    expect(Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq'))->toBeTrue();

    $migration->down();
    expect(Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq'))->toBeFalse()
        ->and(Schema::hasIndex('cotacoes', 'cotacoes_email_externo_id_unique'))->toBeTrue()
        ->and(Schema::hasColumn('cotacoes', 'email_token'))->toBeFalse();

    $migration->up();
    // E rodar de novo sobre a base já migrada não quebra (aditiva/idempotente).
    $migration->up();

    expect(Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq'))->toBeTrue()
        ->and(Schema::hasIndex('cotacoes', 'cotacoes_email_externo_id_unique'))->toBeFalse()
        ->and(Schema::hasColumn('cotacoes', 'email_token'))->toBeTrue();
});

it('não casa mais a resposta pela PK numérica [COT-{id}] (sequencial e global)', function () {
    // 2ª auditoria adversarial: o fallback numérico era o furo. O id é sequencial e
    // GLOBAL na instalação — `[COT-1]`, `[COT-2]`… são chutáveis, e alcançavam a
    // cotação de QUALQUER empresa. Só o ULID opaco casa agora.
    $cotA = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-legado', messageId: '<legado@fornecedor>', de: 'forn@alfa.test',
        assunto: "Re: Solicitação de cotação [COT-{$cotA->id}]", corpo: 'Valor: R$ 50,00',
        autenticacao: autenticadoPor('alfa.test'),
    ));

    expect($res)->toBeNull()
        ->and($cotA->fresh()->resposta_recebida_em)->toBeNull()
        ->and($cotA->fresh()->valor_respondido)->toBeNull();
});

it('enumerar a PK do outro tenant não alcança mais a cotação dele', function () {
    // O atacante é fornecedor do tenant B e sabe (ou chuta) o id da cotação do A.
    $cotA = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');
    cotacaoNoTenant($this->tenantB->id, 'forn@bravo.test');

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-enum', messageId: '<enum@fornecedor>', de: 'forn@alfa.test',
        assunto: "Re: [COT-{$cotA->id}]", corpo: 'Valor: R$ 1,00',
        autenticacao: autenticadoPor('alfa.test'),
    ));

    expect($res)->toBeNull()
        ->and($cotA->fresh()->valor_respondido)->toBeNull();
});

it('recusa a resposta cujo From confere mas sem SPF/DKIM aprovado', function () {
    // O header `From` é texto livre: o passo que o compara não prova nada sozinho.
    $cot = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');

    $forjada = fn (?string $auth) => app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-forjado', messageId: '<forjado-'.uniqid().'@atacante>', de: 'forn@alfa.test',
        assunto: "Re: [COT-{$cot->email_token}]", corpo: 'Valor: R$ 1,00',
        autenticacao: $auth,
    ));

    // (a) sem header nenhum; (b) SPF/DKIM reprovados; (c) aprovados para OUTRO domínio.
    expect($forjada(null))->toBeNull()
        ->and($forjada('mx.helix.test; spf=fail smtp.mailfrom=forn@alfa.test; dkim=fail header.d=alfa.test'))->toBeNull()
        ->and($forjada('mx.helix.test; spf=pass smtp.mailfrom=forn@atacante.test; dkim=pass header.d=atacante.test'))->toBeNull()
        ->and($cot->fresh()->resposta_recebida_em)->toBeNull();

    // E a legítima, com SPF/DKIM do domínio do fornecedor, passa.
    expect($forjada(autenticadoPor('alfa.test')))->not->toBeNull()
        ->and((float) $cot->fresh()->valor_respondido)->toBe(1.00);
});

it('aceita DKIM assinado pelo domínio PAI do remetente, mas não por um subdomínio', function () {
    $cot = cotacaoNoTenant($this->tenantA->id, 'forn@cotacoes.alfa.test');

    $comAuth = fn (string $auth) => app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-align', messageId: '<align-'.uniqid().'@fornecedor>', de: 'forn@cotacoes.alfa.test',
        assunto: "Re: [COT-{$cot->email_token}]", corpo: 'Valor: R$ 7,00',
        autenticacao: $auth,
    ));

    // Subdomínio não fala pelo pai: quem assina por `outra.alfa.test` não autentica
    // `cotacoes.alfa.test`.
    expect($comAuth('mx.helix.test; dkim=pass header.d=outra.alfa.test; dmarc=pass header.from=cotacoes.alfa.test'))->toBeNull();

    // O pai, sim (`alfa.test` assina pelo próprio subdomínio).
    expect($comAuth('mx.helix.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=cotacoes.alfa.test'))->not->toBeNull()
        ->and((float) $cot->fresh()->valor_respondido)->toBe(7.00);
});
