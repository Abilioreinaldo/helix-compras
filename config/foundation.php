<?php

return [
    /*
     * Papéis (slug) cujos usuários são obrigados a ter 2FA, além do admin.
     * Compras: compradora sênior e financeiro.
     */
    'mandatory_2fa_roles' => ['compras', 'financeiro'],

    /*
     * Rota inicial padrão pós-login.
     */
    'home_route' => 'dashboard',
];
