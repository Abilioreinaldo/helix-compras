<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Enums\StatusRequisicao;
use App\Imap\LeitorCaixaCotacoes;
use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoPorEmailRecebida;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\CotacaoLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
| Decisão 11: a resposta por e-mail NÃO grava mais proposta — vira AVISO ao comprador.
| A proposta só é gravada pelo link assinado (Security/CotacaoLinkAssinadoTest).
*/

// authserv-id do NOSSO MX: só o carimbo dele conta (3ª auditoria adversarial).
beforeEach(fn () => config(['mail.imap.authserv_id' => 'mx.helix.test']));

/**
 * Cotação aguardando, com fornecedor (e-mail conhecido), compradora (criadora) e link emitido.
 *
 * @param  array<string, mixed>  $attrs
 */
function cotacaoTeste(array $attrs = []): Cotacao
{
    $fornecedor = Fornecedor::factory()->create(['contato_email' => 'fornecedor@exemplo.com']);
    $compradora = User::factory()->create(['email' => 'compradora@exemplo.com']);

    $cotacao = Cotacao::factory()->create(array_merge([
        // 4ª auditoria (COMPRAS-4): o aviso só sai para cotação que AINDA espera resposta —
        // requisição EM COTAÇÃO. A factory criava a requisição em Rascunho (estado em que
        // não existe cotação aguardando no fluxo real); o cenário agora é o verdadeiro.
        'requisicao_id' => Requisicao::factory()->create(['status' => StatusRequisicao::EmCotacao])->id,
        'fornecedor_id' => $fornecedor->id,
        'criada_por' => $compradora->id,
        'valor' => null,
    ], $attrs));

    app(CotacaoLinkService::class)->emitir($cotacao, now()->addDays(5));

    return $cotacao;
}

function referenciaDe(Cotacao $c): string
{
    return CotacaoLink::withoutTenantScope()->where('cotacao_id', $c->id)->latest('id')->value('referencia');
}

/** @param array<string, mixed> $over */
function mensagemTeste(Cotacao $c, string $corpo, array $over = []): MensagemEmail
{
    $de = $over['de'] ?? 'fornecedor@exemplo.com';
    $dominio = substr((string) strrchr($de, '@'), 1);

    return new MensagemEmail(
        id: $over['id'] ?? 'uid-1',
        messageId: $over['messageId'] ?? '<msg-1@fornecedor>',
        de: $de,
        assunto: $over['assunto'] ?? 'Re: Solicitação de cotação [COT-'.referenciaDe($c).']',
        corpo: $corpo,
        autenticacao: array_key_exists('autenticacao', $over)
            ? $over['autenticacao']
            : "mx.helix.test; spf=pass smtp.mailfrom={$de}; dkim=pass header.d={$dominio}; dmarc=pass header.from={$dominio}",
    );
}

/** Colunas que a resposta por e-mail gravava antes da decisão 11. */
function camposDeProposta(Cotacao $c): array
{
    return collect(DB::table('cotacoes')->where('id', $c->id)->first())
        ->only(['valor', 'valor_respondido', 'prazo_respondido', 'observacoes_fornecedor', 'resposta_recebida_em', 'email_externo_id', 'prazo_entrega_dias', 'validade_proposta', 'updated_at'])
        ->all();
}

// ─── Ação ────────────────────────────────────────────────────────────────────

it('NÃO grava nada na cotação: nem sugestão, nem valor, nem Message-ID', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $antes = camposDeProposta($c);

    $this->travel(1)->minutes();
    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'Valor: R$ 150,00 | Prazo: 15 dias'));

    expect($res)->not->toBeNull()
        ->and(camposDeProposta($c))->toBe($antes)
        ->and(DB::table('itens_cotacao')->where('cotacao_id', $c->id)->count())->toBe(0);
});

it('avisa a compradora com os sinais de remetente e autenticidade', function () {
    Mail::fake();
    $c = cotacaoTeste();

    app(ProcessarRespostaCotacaoAction::class)->execute(mensagemTeste($c, 'Valor: R$ 100,00 em 5 dias'));

    Mail::assertSent(RespostaCotacaoPorEmailRecebida::class, fn ($m) => $m->hasTo('compradora@exemplo.com')
        && $m->cotacao->is($c) && $m->remetenteConfere && $m->autenticado);
});

it('autenticidade e remetente são só SINAIS: e-mail forjado ainda avisa, marcado como não verificado, e não grava', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $antes = camposDeProposta($c);

    $res = app(ProcessarRespostaCotacaoAction::class)->execute(
        mensagemTeste($c, 'Valor: R$ 1,00', ['de' => 'estranho@invasor.com', 'autenticacao' => null])
    );

    expect($res)->not->toBeNull()->and(camposDeProposta($c))->toBe($antes);
    Mail::assertSent(RespostaCotacaoPorEmailRecebida::class, fn ($m) => ! $m->remetenteConfere && ! $m->autenticado);
});

it('não avisa duas vezes o mesmo Message-ID', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $msg = mensagemTeste($c, 'R$ 100,00');

    app(ProcessarRespostaCotacaoAction::class)->execute($msg);
    $res2 = app(ProcessarRespostaCotacaoAction::class)->execute($msg);

    expect($res2)->toBeNull();
    Mail::assertSentCount(1);
});

it('ignora e-mail sem referência de cotação no assunto', function () {
    Mail::fake();
    $c = cotacaoTeste();

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'R$ 100,00', ['assunto' => 'Bom dia, segue nossa proposta']));

    expect($res)->toBeNull();
    Mail::assertNothingSent();
});

it('o antigo email_token (ULID da cotação) não casa mais', function () {
    Mail::fake();
    $c = cotacaoTeste();
    DB::table('cotacoes')->where('id', $c->id)->update(['email_token' => '01J0000000000000000000LEGA']);

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'R$ 100,00', ['assunto' => 'Re: [COT-01J0000000000000000000LEGA]']));

    expect($res)->toBeNull();
    Mail::assertNothingSent();
});

// ─── DTO ─────────────────────────────────────────────────────────────────────

it('detecta mensagens automáticas (auto-reply / noreply)', function () {
    expect((new MensagemEmail('1', '<a>', 'noreply@x.com', 'Re: [COT-1]', 'x'))->ehAutomatica())->toBeTrue()
        ->and((new MensagemEmail('1', '<a>', 'forn@x.com', 'Auto Reply: ausente', 'x'))->ehAutomatica())->toBeTrue()
        ->and((new MensagemEmail('1', '<a>', 'forn@x.com', 'Re: [COT-1]', 'x'))->ehAutomatica())->toBeFalse();
});

// ─── Command (com leitor fake) ───────────────────────────────────────────────

function leitorFake(array $mensagens): LeitorCaixaCotacoes
{
    return new class($mensagens) implements LeitorCaixaCotacoes
    {
        /** @var array<int, string> */
        public array $lidas = [];

        /** @param array<int, MensagemEmail> $mensagens */
        public function __construct(private array $mensagens) {}

        public function naoLidas(): array
        {
            return $this->mensagens;
        }

        public function marcarComoLida(string $id): void
        {
            $this->lidas[] = $id;
        }
    };
}

it('command avisa a compradora, não grava proposta e marca como lida', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $antes = camposDeProposta($c);
    $fake = leitorFake([mensagemTeste($c, 'Valor: R$ 250,00 | Prazo: 8 dias')]);
    app()->instance(LeitorCaixaCotacoes::class, $fake);

    $this->artisan('cotacoes:capturar-respostas')->assertExitCode(0);

    expect(camposDeProposta($c))->toBe($antes)
        ->and($fake->lidas)->toContain('uid-1');
    Mail::assertSent(RespostaCotacaoPorEmailRecebida::class);
});

it('command descarta auto-reply sem processar', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $auto = mensagemTeste($c, 'R$ 1,00', ['de' => 'noreply@x.com', 'id' => 'uid-auto']);
    $fake = leitorFake([$auto]);
    app()->instance(LeitorCaixaCotacoes::class, $fake);

    $this->artisan('cotacoes:capturar-respostas')->assertExitCode(0);

    expect($fake->lidas)->toContain('uid-auto'); // marcada lida (descartada)
    Mail::assertNothingSent();
});
