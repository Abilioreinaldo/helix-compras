<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\MensagemEmail;
use App\Mail\SolicitacaoCotacao;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Models\Platform\Identity\TenantFeature;
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

    $this->tenantA = Tenant::create(['slug' => 'alfa-imap', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-imap', 'name' => 'Bravo', 'status' => 'active']);

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        TenantFeature::firstOrCreate(['tenant_id' => $tenant->id, 'feature' => 'compras'], ['enabled' => true]);
    }

    TenantContext::forget();
});

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
    ));

    $res = $acao->execute(new MensagemEmail(
        id: 'uid-b', messageId: $messageId, de: 'forn@bravo.test',
        assunto: "Re: cotação [COT-{$cotB->email_token}]", corpo: 'Valor: R$ 200,00',
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

it('ainda aceita o formato antigo [COT-{id}] de e-mails já em trânsito', function () {
    $cot = cotacaoNoTenant($this->tenantA->id, 'forn@alfa.test');

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-legado', messageId: '<legado@fornecedor>', de: 'forn@alfa.test',
        assunto: "Re: Solicitação de cotação [COT-{$cot->id}]", corpo: 'Valor: R$ 50,00',
    ));

    expect($res)->not->toBeNull()
        ->and((float) $cot->fresh()->valor_respondido)->toBe(50.00);
});
