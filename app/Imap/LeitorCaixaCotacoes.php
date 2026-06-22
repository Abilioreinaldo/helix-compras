<?php

namespace App\Imap;

/**
 * Contrato de leitura da caixa de cotações. A implementação concreta (webklex/php-imap)
 * fica isolada no adaptador; o Job/Command e os testes dependem só desta interface.
 */
interface LeitorCaixaCotacoes
{
    /**
     * Mensagens não lidas (UNSEEN) da caixa.
     *
     * @return array<int, MensagemEmail>
     */
    public function naoLidas(): array;

    /** Marca a mensagem como lida (evita reprocessar na próxima rodada). */
    public function marcarComoLida(string $id): void;
}
