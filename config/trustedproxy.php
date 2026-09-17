<?php

/*
| Proxies confiáveis (lido pelo TrustProxies do framework quando
| trustProxies(at:) não é informado no bootstrap/app.php).
|
| TRUSTED_PROXIES: CSV de IPs/CIDRs do balanceador (ex.: 10.0.0.0/8) ou "*"
| para confiar no IP que chamou (só atrás de um LB que não é alcançável
| diretamente). Vazio = nenhum proxy confiado (comportamento de antes).
*/

return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,

    /*
    | Hosts confiáveis (fundação v0.7.0 / 4ª auditoria, FUNDACAO-2).
    |
    | Sem `trustHosts()` o Laravel aceita QUALQUER cabeçalho Host, e todo
    | `url()`/`route()` do app (não só os links da fundação) pode ser envenenado:
    | um POST com `Host: evil.attacker.test` na ação que dispara o link de senha
    | fazia o e-mail legítimo levar o token para o atacante.
    |
    | TRUSTED_HOSTS: CSV de domínios (sem esquema). Vazio = o host do APP_URL,
    | que é a raiz dos links de e-mail e já é obrigatório em produção. Assim o
    | deploy mínimo (APP_URL fixo) já nasce com o Host amarrado. Lido daqui (e não
    | por env() no bootstrap) para sobreviver ao config:cache.
    */
    'hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (env('TRUSTED_HOSTS') ?: (parse_url((string) env('APP_URL'), PHP_URL_HOST) ?? ''))),
    ), static fn (string $host): bool => $host !== '')),
];
