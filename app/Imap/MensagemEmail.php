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
        // Headers `Authentication-Results` CRUS, na ordem do cabeçalho (do TOPO para
        // baixo) — uma string é tratada como um único header. `de` (From) é texto
        // livre escolhido por quem envia; a evidência de autenticidade é o carimbo
        // do NOSSO servidor de entrada, e só ele (ver VerificadorAutenticidadeEmail).
        // NUNCA concatene vários headers numa string: o remetente também escreve
        // `Authentication-Results`, e juntar tudo foi o bypass da 3ª auditoria.
        public string|array|null $autenticacao = null,
    ) {}

    /** @return list<string> headers Authentication-Results, do topo para baixo */
    public function cabecalhosAutenticacao(): array
    {
        if ($this->autenticacao === null) {
            return [];
        }

        return array_values(array_map('strval', (array) $this->autenticacao));
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
