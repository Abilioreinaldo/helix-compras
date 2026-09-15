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
];
