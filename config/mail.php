<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caixa IMAP de cotações
    |--------------------------------------------------------------------------
    |
    | Caixa dedicada que recebe as respostas dos fornecedores. O command
    | `cotacoes:capturar-respostas` lê os e-mails não lidos e registra a sugestão
    | de valor/prazo. Sem IMAP_HOST configurado, a captura é simplesmente ignorada.
    |
    */

    'imap' => [
        'host' => env('IMAP_HOST'),
        'port' => env('IMAP_PORT', 993),
        'username' => env('IMAP_USERNAME'),
        'password' => env('IMAP_PASSWORD'),
        'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
        'mailbox' => env('IMAP_MAILBOX', 'INBOX'),

        /*
         * Exigir a autenticidade do remetente (SPF/DKIM + DMARC, carimbados pelo NOSSO
         * servidor de entrada no `Authentication-Results`) antes de gravar a resposta
         * da cotação. LIGADO por padrão: sem isto, a única prova de origem é o header
         * `From`, que é texto livre.
         *
         * FAIL-CLOSED DE VERDADE (3ª auditoria): só desliga com valor falso EXPLÍCITO
         * (false/0/off/no). A versão anterior usava filter_var(..., FILTER_NULL_ON_FAILURE)
         * e o comentário dizia que vazio não desligava — mas filter_var('') devolve
         * FALSE (não null): `IMAP_EXIGIR_AUTENTICACAO=` DESLIGAVA a verificação.
         */
        'exigir_autenticacao' => (static function (): bool {
            $valor = env('IMAP_EXIGIR_AUTENTICACAO');

            if ($valor === false) { // "false" / "(false)" — o env() já converte
                return false;
            }

            return ! in_array(strtolower(trim((string) $valor)), ['0', 'off', 'no', 'false'], true);
        })(),

        /*
         * authserv-id CONFIÁVEL: o identificador que o NOSSO MTA de entrada escreve no
         * início do `Authentication-Results` (ex.: `mx.empresa.com.br`; Google
         * Workspace = `mx.google.com`). Só o PRIMEIRO carimbo do topo com este id é
         * lido. VAZIO = nenhuma resposta é aceita e a captura nem abre a caixa
         * (fail-closed). Requisito de INFRA: o MTA deve REMOVER os A-R que chegam de
         * fora com este id (RFC 8601 §5) e carimbar o seu no topo, em RFC 8601 (o
         * M365 escreve sem authserv-id — não serve sem um filtro de borda que carimbe).
         */
        'authserv_id' => env('IMAP_AUTHSERV_ID'),

        /*
         * Domínios de webmail PÚBLICO: o domínio não identifica o fornecedor (qualquer
         * um abre conta), então exige-se o e-mail EXATO no SPF (`smtp.mailfrom`) ou no
         * DKIM (`header.i`) — não basta DKIM/DMARC de gmail.com.
         */
        'dominios_webmail_publico' => [
            'gmail.com', 'googlemail.com', 'outlook.com', 'outlook.com.br', 'hotmail.com',
            'hotmail.com.br', 'live.com', 'msn.com', 'yahoo.com', 'yahoo.com.br',
            'ymail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'uol.com.br',
            'bol.com.br', 'terra.com.br', 'ig.com.br', 'globo.com', 'globomail.com',
            'r7.com', 'zoho.com', 'proton.me', 'protonmail.com', 'gmx.com', 'gmx.net',
            'yandex.com', 'mail.com',
        ],    ],

];
