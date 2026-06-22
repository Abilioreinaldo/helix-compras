<?php

namespace App\Imap;

use Illuminate\Support\Facades\Log;

/**
 * Fallback usado quando o IMAP não está configurado (sem host no config/mail.php).
 * Não conecta em nada — apenas registra e devolve vazio, para o Command não quebrar.
 */
class LeitorCaixaCotacoesIndisponivel implements LeitorCaixaCotacoes
{
    public function naoLidas(): array
    {
        Log::info('Captura IMAP de cotações ignorada: IMAP não configurado (mail.imap.host vazio).');

        return [];
    }

    public function marcarComoLida(string $id): void
    {
        // no-op
    }
}
