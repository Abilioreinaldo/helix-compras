<?php

namespace App\Imap;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Implementação concreta do leitor da caixa de cotações sobre webklex/php-imap (puro PHP,
 * não exige a extensão ext-imap). Usada apenas em runtime; os testes usam um fake.
 *
 * Lê com PEEK (não marca como lida ao buscar o corpo) — o Command marca explicitamente
 * via marcarComoLida() só depois de processar.
 */
class WebklexLeitorCaixaCotacoes implements LeitorCaixaCotacoes
{
    /** @var array<string, Message> uid => mensagem (mantém a conexão viva entre buscar e marcar) */
    private array $mensagens = [];

    public function naoLidas(): array
    {
        $config = config('mail.imap');

        $cliente = (new ClientManager)->make([
            'host' => $config['host'],
            'port' => (int) $config['port'],
            'encryption' => $config['encryption'] ?: false,
            'validate_cert' => true,
            'username' => $config['username'],
            'password' => $config['password'],
            'protocol' => 'imap',
        ]);

        $cliente->connect();
        $pasta = $cliente->getFolder($config['mailbox'] ?? 'INBOX');

        $consulta = $pasta->query()->unseen();
        if (method_exists($consulta, 'leaveUnread')) {
            $consulta->leaveUnread();
        }

        $resultado = [];
        foreach ($consulta->get() as $mensagem) {
            /** @var Message $mensagem */
            $uid = (string) $mensagem->getUid();
            $this->mensagens[$uid] = $mensagem;

            $de = $mensagem->getFrom()[0] ?? null;

            $resultado[] = new MensagemEmail(
                id: $uid,
                messageId: (string) $mensagem->getMessageId(),
                de: (string) ($de->mail ?? ''),
                assunto: (string) $mensagem->getSubject(),
                corpo: (string) ($mensagem->getTextBody() ?: $mensagem->getHTMLBody()),
                autenticacao: $this->autenticacao($mensagem),
            );
        }

        return $resultado;
    }

    /**
     * Header `Authentication-Results` cru (SPF/DKIM/DMARC apurados pelo servidor de
     * entrada). Pode haver mais de um (relay encadeado) — concatenamos todos; o
     * consumidor exige alinhamento com o domínio do fornecedor, então um carimbo de
     * relay intermediário não "aprova" ninguém sozinho. Ausente = null (fail-closed
     * do lado de quem consome).
     */
    private function autenticacao(Message $mensagem): ?string
    {
        try {
            $atributo = $mensagem->getHeader()->get('authentication_results');
        } catch (\Throwable) {
            return null;
        }

        if ($atributo === null) {
            return null;
        }

        $valores = method_exists($atributo, 'toArray') ? (array) $atributo->toArray() : [$atributo];
        $texto = trim(implode('; ', array_map(fn ($v) => (string) $v, $valores)));

        return $texto === '' ? null : $texto;
    }

    public function marcarComoLida(string $id): void
    {
        if (isset($this->mensagens[$id])) {
            $this->mensagens[$id]->setFlag('Seen');
        }
    }
}
