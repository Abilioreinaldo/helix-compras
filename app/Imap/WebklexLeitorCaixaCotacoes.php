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
     * Headers `Authentication-Results` crus, UM POR ELEMENTO, na ordem em que estão
     * no cabeçalho (do topo para baixo).
     *
     * Antes (3ª auditoria adversarial) todos eram concatenados numa string — e o
     * remetente também escreve `Authentication-Results`: bastava anexar
     * `mx.nosso; dkim=pass header.d=fornecedor` para o carimbo forjado entrar no
     * mesmo texto que o real. A ordem importa porque só o PRIMEIRO carimbo com o
     * nosso authserv-id vale (VerificadorAutenticidadeEmail). Lemos do cabeçalho
     * BRUTO para não depender de como a biblioteca agrupa/ordena repetições.
     *
     * @return list<string>
     */
    private function autenticacao(Message $mensagem): array
    {
        try {
            $bruto = (string) $mensagem->getHeader()?->raw;
        } catch (\Throwable) {
            return [];
        }

        return self::authenticationResultsDoCabecalho($bruto);
    }

    /**
     * Extrai, do cabeçalho bruto, os valores de `Authentication-Results` em ordem,
     * desdobrando linhas continuadas (RFC 5322 §2.2.3). Puro — testável sem IMAP.
     *
     * @return list<string>
     */
    public static function authenticationResultsDoCabecalho(string $bruto): array
    {
        // Só o bloco de cabeçalho (até a primeira linha vazia).
        $bruto = preg_split('/\r?\n\r?\n/', $bruto, 2)[0] ?? '';
        $desdobrado = preg_replace('/\r?\n[ \t]+/', ' ', $bruto) ?? '';

        $valores = [];
        foreach (preg_split('/\r?\n/', $desdobrado) ?: [] as $linha) {
            if (preg_match('/^authentication-results[ \t]*:(.*)$/i', $linha, $m)) {
                $valor = trim($m[1]);
                if ($valor !== '') {
                    $valores[] = $valor;
                }
            }
        }

        return $valores;
    }

    public function marcarComoLida(string $id): void
    {
        if (isset($this->mensagens[$id])) {
            $this->mensagens[$id]->setFlag('Seen');
        }
    }
}
