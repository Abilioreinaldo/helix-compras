<?php

namespace App\Imap;

/**
 * DTO de uma mensagem lida da caixa de cotações — desacopla o restante do sistema
 * da biblioteca de IMAP concreta. Testes constroem este objeto diretamente.
 */
final readonly class MensagemEmail
{
    public function __construct(
        public string $id,        // identificador na caixa (para marcar como lida)
        public string $messageId, // header Message-ID (idempotência)
        public string $de,        // e-mail do remetente
        public string $assunto,
        public string $corpo,
        // Header `Authentication-Results` cru, como o servidor de entrada o escreveu.
        // `de` (From) é texto livre escolhido por quem envia — forjá-lo é trivial. A
        // única evidência de autenticidade que chega junto da mensagem é esta.
        public ?string $autenticacao = null,
    ) {}

    /**
     * SPF ou DKIM aprovados e ALINHADOS com o domínio esperado (o do fornecedor da
     * cotação)? Fail-closed: sem header, header ilegível ou domínio de outro
     * remetente → false.
     *
     * "Alinhado" = o domínio que assinou/autorizou é o esperado ou um pai dele
     * (`alfa.test` assina por `cotacoes.alfa.test`). Nunca o contrário: um
     * subdomínio não fala pelo pai.
     */
    public function autenticadaPara(string $dominioEsperado): bool
    {
        $esperado = mb_strtolower(trim($dominioEsperado));
        $cabecalho = mb_strtolower(trim((string) $this->autenticacao));

        if ($esperado === '' || $cabecalho === '') {
            return false;
        }

        // DKIM: assinatura criptográfica; header.d é quem assinou.
        if (preg_match_all('/dkim=pass[^;]*?header\.d=([a-z0-9.\-]+)/', $cabecalho, $m)) {
            foreach ($m[1] as $assinante) {
                if ($this->dominioAlinhado(trim($assinante, '.'), $esperado)) {
                    return true;
                }
            }
        }

        // SPF: o IP de origem está autorizado pelo domínio do envelope.
        if (preg_match_all('/spf=pass[^;]*?smtp\.(?:mailfrom|helo)=([^\s;]+)/', $cabecalho, $m)) {
            foreach ($m[1] as $envelope) {
                $envelope = trim($envelope, '<>"\' ');
                $dominio = str_contains($envelope, '@')
                    ? substr((string) strrchr($envelope, '@'), 1)
                    : $envelope;

                if ($this->dominioAlinhado(trim($dominio, '.'), $esperado)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function dominioAlinhado(string $autorizador, string $esperado): bool
    {
        return $autorizador !== ''
            && ($autorizador === $esperado || str_ends_with($esperado, '.'.$autorizador));
    }

    /** Detecta auto-respostas, bounces e remetentes automáticos (não processar). */
    public function ehAutomatica(): bool
    {
        $assunto = mb_strtolower($this->assunto);
        $de = mb_strtolower($this->de);

        foreach (['auto reply', 'autoreply', 'automatic reply', 'out of office', 'ausência do escritório'] as $marca) {
            if (str_contains($assunto, $marca)) {
                return true;
            }
        }

        foreach (['noreply', 'no-reply', 'mailer-daemon', 'postmaster'] as $marca) {
            if (str_contains($de, $marca)) {
                return true;
            }
        }

        return false;
    }
}
