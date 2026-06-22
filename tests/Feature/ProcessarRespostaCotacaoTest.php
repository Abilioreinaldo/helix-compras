<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\LeitorCaixaCotacoes;
use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoRecebida;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Cria uma cotação com fornecedor (e-mail conhecido) e compradora (criadora).
 *
 * @param  array<string, mixed>  $attrs
 */
function cotacaoTeste(array $attrs = []): Cotacao
{
    $fornecedor = Fornecedor::factory()->create(['contato_email' => 'fornecedor@exemplo.com']);
    $compradora = User::factory()->create(['email' => 'compradora@exemplo.com']);

    return Cotacao::factory()->create(array_merge([
        'fornecedor_id' => $fornecedor->id,
        'criada_por' => $compradora->id,
        'valor' => null,
    ], $attrs));
}

/** @param array<string, mixed> $over */
function mensagemTeste(Cotacao $c, string $corpo, array $over = []): MensagemEmail
{
    return new MensagemEmail(
        id: $over['id'] ?? 'uid-1',
        messageId: $over['messageId'] ?? '<msg-1@fornecedor>',
        de: $over['de'] ?? 'fornecedor@exemplo.com',
        assunto: $over['assunto'] ?? "Re: Solicitação de cotação [COT-{$c->id}]",
        corpo: $corpo,
    );
}

// ─── Ação ────────────────────────────────────────────────────────────────────

it('registra a resposta nos campos advisory sem tocar no valor oficial', function () {
    Mail::fake();
    $c = cotacaoTeste();

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'Valor: R$ 150,00 | Prazo: 15 dias'));

    $c->refresh();
    expect($res)->not->toBeNull()
        ->and((float) $c->valor_respondido)->toBe(150.00)
        ->and($c->prazo_respondido)->toBe(15)
        ->and($c->resposta_recebida_em)->not->toBeNull()
        ->and($c->email_externo_id)->toBe('<msg-1@fornecedor>')
        ->and($c->valor)->toBeNull(); // valor oficial permanece intocado
});

it('não duplica quando o mesmo Message-ID chega duas vezes', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $msg = mensagemTeste($c, 'R$ 100,00');

    app(ProcessarRespostaCotacaoAction::class)->execute($msg);
    $res2 = app(ProcessarRespostaCotacaoAction::class)->execute($msg);

    expect($res2)->toBeNull()
        ->and(Cotacao::where('email_externo_id', $msg->messageId)->count())->toBe(1);
});

it('rejeita resposta de remetente que não é o fornecedor da cotação', function () {
    Mail::fake();
    $c = cotacaoTeste();

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'R$ 100,00', ['de' => 'estranho@invasor.com']));

    expect($res)->toBeNull()
        ->and($c->fresh()->resposta_recebida_em)->toBeNull();
});

it('ignora e-mail sem token de cotação no assunto', function () {
    Mail::fake();
    $c = cotacaoTeste();

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'R$ 100,00', ['assunto' => 'Bom dia, segue nossa proposta']));

    expect($res)->toBeNull()
        ->and($c->fresh()->resposta_recebida_em)->toBeNull();
});

it('ignora cotação que já foi respondida (rate limit)', function () {
    Mail::fake();
    $c = cotacaoTeste(['resposta_recebida_em' => now()->subDay()]);

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'R$ 999,00', ['messageId' => '<novo@fornecedor>']));

    expect($res)->toBeNull();
});

it('quando o parser não acha valor, registra a resposta como fallback manual', function () {
    Mail::fake();
    $c = cotacaoTeste();

    $res = app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'Bom dia, conseguimos atender. Retorno em breve com os números.'));

    $c->refresh();
    expect($res)->not->toBeNull()
        ->and($c->valor_respondido)->toBeNull()
        ->and($c->resposta_recebida_em)->not->toBeNull()
        ->and($c->observacoes_fornecedor)->toContain('Bom dia');
    Mail::assertSent(RespostaCotacaoRecebida::class); // mesmo sem valor, notifica
});

it('notifica a compradora que criou a cotação', function () {
    Mail::fake();
    $c = cotacaoTeste();

    app(ProcessarRespostaCotacaoAction::class)
        ->execute(mensagemTeste($c, 'Valor: R$ 100,00 em 5 dias'));

    Mail::assertSent(RespostaCotacaoRecebida::class, fn ($m) => $m->hasTo('compradora@exemplo.com') && $m->cotacao->is($c));
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

it('command captura respostas e marca como lida', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $fake = leitorFake([mensagemTeste($c, 'Valor: R$ 250,00 | Prazo: 8 dias')]);
    app()->instance(LeitorCaixaCotacoes::class, $fake);

    $this->artisan('cotacoes:capturar-respostas')->assertExitCode(0);

    $c->refresh();
    expect((float) $c->valor_respondido)->toBe(250.00)
        ->and($c->prazo_respondido)->toBe(8)
        ->and($fake->lidas)->toContain('uid-1');
});

it('command descarta auto-reply sem processar', function () {
    Mail::fake();
    $c = cotacaoTeste();
    $auto = mensagemTeste($c, 'R$ 1,00', ['de' => 'noreply@x.com', 'id' => 'uid-auto']);
    $fake = leitorFake([$auto]);
    app()->instance(LeitorCaixaCotacoes::class, $fake);

    $this->artisan('cotacoes:capturar-respostas')->assertExitCode(0);

    expect($c->fresh()->resposta_recebida_em)->toBeNull()
        ->and($fake->lidas)->toContain('uid-auto'); // marcada lida (descartada)
    Mail::assertNothingSent();
});
