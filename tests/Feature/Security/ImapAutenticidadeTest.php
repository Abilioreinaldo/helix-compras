<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\AuthenticationResults;
use App\Imap\LeitorCaixaCotacoes;
use App\Imap\MensagemEmail;
use App\Imap\WebklexLeitorCaixaCotacoes;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
| 3ª auditoria adversarial — achados 5 (ALTO) e 6 (MÉDIO) do Compras.
|
| A verificação SPF/DKIM da resposta de cotação por e-mail era contornável: juntava
| TODOS os `Authentication-Results` (inclusive os que o próprio remetente escreve),
| não conferia o authserv-id, casava por regex `[^;]*?` (sem respeitar comentário
| nem aspas) e ignorava DMARC. Cada caso abaixo é uma variação da sonda da auditoria
| (scratchpad `sonda_imap.php`) e passava ANTES da correção.
*/

const IMAP_AUTHSERV = 'mx.helix.test';

beforeEach(function () {
    Mail::fake();
    config(['mail.imap.authserv_id' => IMAP_AUTHSERV, 'mail.imap.exigir_autenticacao' => true]);
});

/** Cotação aguardando resposta, com o fornecedor no e-mail informado. */
function cotacaoImap(string $emailFornecedor): Cotacao
{
    $fornecedor = Fornecedor::factory()->create(['contato_email' => $emailFornecedor]);

    return Cotacao::factory()->create([
        'fornecedor_id' => $fornecedor->id,
        'criada_por' => User::factory()->create()->id,
        'valor' => null,
    ]);
}

/** Processa uma "resposta" cujo From confere com o fornecedor; devolve se foi gravada. */
function respostaGravada(Cotacao $cotacao, string $de, string|array|null $autenticacao): bool
{
    $resultado = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-'.uniqid(),
        messageId: '<'.uniqid().'@remetente>',
        de: $de,
        assunto: "Re: [COT-{$cotacao->email_token}]",
        corpo: 'Valor: R$ 1,00',
        autenticacao: $autenticacao,
    ));

    return $resultado !== null && $cotacao->fresh()->resposta_recebida_em !== null;
}

// ───────── Achado 5: casos da sonda ─────────

it('recusa A-R forjado pelo remetente concatenado ao carimbo real (webklex juntava todos)', function (string $juntado) {
    $cot = cotacaoImap('forn@alfa.test');

    expect(respostaGravada($cot, 'forn@alfa.test', $juntado))->toBeFalse();
})->with([
    '2 headers → implode " "' => 'mx.helix.test; spf=fail smtp.mailfrom=a@evil.test; dkim=none mx.helix.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=alfa.test',
    '3+ headers → implode "; "' => 'mx.helix.test; spf=fail smtp.mailfrom=a@evil.test; dkim=none; mx.helix.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=alfa.test; x',
]);

it('considera só o PRIMEIRO A-R do topo com o authserv-id confiável', function () {
    $cot = cotacaoImap('forn@alfa.test');

    // Topo = carimbo do NOSSO MX (reprovado). Abaixo, o que o atacante escreveu
    // antes de enviar — com o mesmo authserv-id e tudo "pass".
    $headers = [
        'mx.helix.test; spf=fail smtp.mailfrom=a@evil.test; dkim=none; dmarc=fail header.from=alfa.test',
        'mx.helix.test; spf=pass smtp.mailfrom=forn@alfa.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=alfa.test',
    ];

    expect(respostaGravada($cot, 'forn@alfa.test', $headers))->toBeFalse();
});

it('respeita aspas: local-part citado com ";dkim=pass" não vira resultado', function () {
    $cot = cotacaoImap('forn@alfa.test');

    expect(respostaGravada($cot, 'forn@alfa.test',
        'mx.helix.test; spf=pass smtp.mailfrom="x;dkim=pass header.d=alfa.test"@evil.test; dkim=none; dmarc=none header.from=alfa.test'
    ))->toBeFalse();
});

it('ignora carimbo de authserv-id que não é o confiável (relay do atacante)', function () {
    $cot = cotacaoImap('forn@alfa.test');

    expect(respostaGravada($cot, 'forn@alfa.test',
        'relay.evil.test; spf=pass smtp.mailfrom=forn@alfa.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=alfa.test'
    ))->toBeFalse();
});

it('fornecedor em webmail público exige o e-mail EXATO, não só o domínio', function () {
    $cot = cotacaoImap('forn@gmail.com');

    // Atacante com conta Gmail própria: DKIM/SPF/DMARC de gmail.com são legítimos,
    // mas autenticam OUTRA conta.
    expect(respostaGravada($cot, 'forn@gmail.com',
        'mx.helix.test; dkim=pass header.i=@gmail.com header.d=gmail.com; spf=pass smtp.mailfrom=atacante@gmail.com; dmarc=pass header.from=gmail.com'
    ))->toBeFalse();

    // O próprio fornecedor (envelope = a conta dele) passa.
    expect(respostaGravada($cot, 'forn@gmail.com',
        'mx.helix.test; dkim=pass header.i=@gmail.com header.d=gmail.com; spf=pass smtp.mailfrom=forn@gmail.com; dmarc=pass header.from=gmail.com'
    ))->toBeTrue();
});

it('exige dmarc=pass alinhado ao domínio do fornecedor', function (string $cabecalho) {
    $cot = cotacaoImap('forn@alfa.test');

    expect(respostaGravada($cot, 'forn@alfa.test', $cabecalho))->toBeFalse();
})->with([
    'dmarc=fail com SPF só de HELO' => 'mx.helix.test; spf=pass smtp.helo=alfa.test; dmarc=fail header.from=alfa.test',
    'SPF+DKIM pass sem DMARC' => 'mx.helix.test; spf=pass smtp.mailfrom=forn@alfa.test; dkim=pass header.d=alfa.test',
    'dmarc=pass de OUTRO header.from' => 'mx.helix.test; spf=pass smtp.mailfrom=forn@alfa.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=evil.test',
    'dmarc=pass mas SPF só de HELO e sem DKIM' => 'mx.helix.test; spf=pass smtp.helo=alfa.test; dmarc=pass header.from=alfa.test',
]);

it('aceita o carimbo legítimo do opendkim/opendmarc com comentário contendo ";"', function () {
    $cot = cotacaoImap('forn@alfa.test');

    expect(respostaGravada($cot, 'forn@alfa.test',
        'mx.helix.test; dkim=pass (2048-bit key; unprotected) header.d=alfa.test header.i=@alfa.test header.b="AbC1+dE2"; dmarc=pass (p=none dis=none) header.from=alfa.test'
    ))->toBeTrue();
});

it('sem authserv-id confiável configurado, nada é aceito (fail-closed)', function () {
    config(['mail.imap.authserv_id' => null]);
    $cot = cotacaoImap('forn@alfa.test');

    expect(respostaGravada($cot, 'forn@alfa.test',
        'mx.helix.test; spf=pass smtp.mailfrom=forn@alfa.test; dkim=pass header.d=alfa.test; dmarc=pass header.from=alfa.test'
    ))->toBeFalse();
});

it('a captura não toca a caixa quando a verificação exige authserv-id e ele falta', function () {
    config(['mail.imap.authserv_id' => '']);

    $leitor = new class implements LeitorCaixaCotacoes
    {
        public bool $leu = false;

        public array $lidas = [];

        public function naoLidas(): array
        {
            $this->leu = true;

            return [new MensagemEmail('1', '<a@b>', 'forn@alfa.test', '[COT-X]', 'R$ 1,00')];
        }

        public function marcarComoLida(string $id): void
        {
            $this->lidas[] = $id;
        }
    };
    app()->instance(LeitorCaixaCotacoes::class, $leitor);

    // Sem esta guarda cada resposta seria recusada E marcada como lida: some da
    // caixa sem nunca ter sido avaliada.
    $this->artisan('cotacoes:capturar-respostas')->assertFailed();

    expect($leitor->leu)->toBeFalse()
        ->and($leitor->lidas)->toBe([]);
});

// ───────── Parser RFC 8601 e extração ordenada ─────────

it('parser RFC 8601 respeita comentários aninhados, aspas e reason/props', function () {
    $ar = AuthenticationResults::parse(
        'mx.helix.test 1; dkim=pass (2048-bit key; (aninhado; x)) reason="ok; tudo" header.d=alfa.test header.b="a=b;c"; spf=pass smtp.mailfrom="x;y"@evil.test'
    );

    expect($ar)->not->toBeNull()
        ->and($ar->authservId)->toBe('mx.helix.test')
        ->and($ar->resultados)->toHaveCount(2)
        ->and($ar->resultados[0]['metodo'])->toBe('dkim')
        ->and($ar->resultados[0]['resultado'])->toBe('pass')
        ->and($ar->resultados[0]['props']['header.d'])->toBe('alfa.test')
        ->and($ar->resultados[0]['props']['reason'])->toBe('ok; tudo')
        ->and($ar->resultados[1]['props']['smtp.mailfrom'])->toBe('x;y@evil.test');
});

it('parser recusa header malformado em vez de "adivinhar"', function (string $cabecalho) {
    expect(AuthenticationResults::parse($cabecalho))->toBeNull();
})->with([
    'sem authserv-id (estilo M365)' => 'spf=pass smtp.mailfrom=alfa.test',
    'dois carimbos colados' => 'mx.helix.test; dkim=none mx.helix.test; dkim=pass header.d=alfa.test',
    'comentário aberto' => 'mx.helix.test; dkim=pass (sem fim header.d=alfa.test',
    'aspas abertas' => 'mx.helix.test; dkim=pass header.d="alfa.test',
    'vazio' => '',
]);

it('extrai os Authentication-Results do cabeçalho bruto em ordem, do topo, desdobrando linhas', function () {
    $bruto = "Return-Path: <forn@alfa.test>\r\n"
        ."Authentication-Results: mx.helix.test;\r\n"
        ."\tspf=fail smtp.mailfrom=a@evil.test\r\n"
        ."Received: from x\r\n"
        ."authentication-results: mx.helix.test; dkim=pass header.d=alfa.test\r\n"
        ."Subject: [COT-X]\r\n";

    expect(WebklexLeitorCaixaCotacoes::authenticationResultsDoCabecalho($bruto))->toBe([
        'mx.helix.test; spf=fail smtp.mailfrom=a@evil.test',
        'mx.helix.test; dkim=pass header.d=alfa.test',
    ]);
});

// ───────── Achado 6: IMAP_EXIGIR_AUTENTICACAO= vazia desligava a verificação ─────────

/**
 * Carrega config/mail.php com IMAP_EXIGIR_AUTENTICACAO no valor dado nos TRÊS
 * repositórios que o env() lê (null = ausente) e restaura tudo no fim.
 */
function exigirAutenticacaoCom(?string $valor): mixed
{
    $var = 'IMAP_EXIGIR_AUTENTICACAO';
    $antes = [$_ENV[$var] ?? null, $_SERVER[$var] ?? null, getenv($var)];

    $definir = function (?string $env, ?string $server, string|false|null $putenv) use ($var): void {
        if ($env === null) {
            unset($_ENV[$var]);
        } else {
            $_ENV[$var] = $env;
        }

        if ($server === null) {
            unset($_SERVER[$var]);
        } else {
            $_SERVER[$var] = $server;
        }

        if ($putenv === null || $putenv === false) {
            putenv($var);
        } else {
            putenv("{$var}={$putenv}");
        }
    };

    $definir($valor, $valor, $valor);

    try {
        return (require config_path('mail.php'))['imap']['exigir_autenticacao'];
    } finally {
        $definir(...$antes);
    }
}

it('IMAP_EXIGIR_AUTENTICACAO declarada e VAZIA mantém a verificação LIGADA', function () {
    expect(exigirAutenticacaoCom(''))->toBeTrue()
        ->and(exigirAutenticacaoCom(null))->toBeTrue()
        ->and(exigirAutenticacaoCom('true'))->toBeTrue()
        ->and(exigirAutenticacaoCom('lixo'))->toBeTrue();
});

it('IMAP_EXIGIR_AUTENTICACAO só desliga com valor falso EXPLÍCITO', function (string $valor) {
    expect(exigirAutenticacaoCom($valor))->toBeFalse();
})->with(['false', '0', 'off', 'no']);
