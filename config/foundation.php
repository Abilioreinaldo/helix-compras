<?php

return [
    /*
     * Tenancy fail-closed (helix/foundation v0.2.0).
     *
     * Os dois são LITERAIS `true` — sem env. Ler de env aqui era um risco real:
     * `HELIX_TENANCY_STRICT=` (declarada e VAZIA, coisa que acontece em .env
     * copiado/gerado por pipeline) faz env() devolver string vazia, que
     * filter_var(..., FILTER_VALIDATE_BOOL) converte para FALSE — ou seja, uma
     * env vazia DESLIGAVA o fail-closed silenciosamente. Afrouxar passa a exigir
     * edição do código, que é revisável em PR.
     *  - strict:        consultar/criar sem tenant no contexto lança.
     *  - enforce_stamp: carimbo obrigatório e tenant_id imutável (lança).
     */
    'tenancy' => [
        'strict' => true,
        'enforce_stamp' => true,

        /*
         * v0.3.0 — rampa de compatibilidade do superadmin na guarda de argumento
         * de TenantContext::assertArgumentMatches(). O Compras não tem tela
         * cross-tenant nem operador de plataforma: aqui a rampa nunca foi
         * necessária, então já nasce DESLIGADA (o default do pacote é true, e
         * sai na v0.4). Quem governa tenant alheio é o helix-admin.
         */
        'superadmin_cross_tenant_argument' => false,

        // mergeConfigFrom é RASO: declarar 'tenancy' aqui substitui o bloco
        // inteiro do pacote, então o off-boarding precisa vir junto.
        'offboarding' => [
            // Declaradas FORA do expurgo/exportação do tenant, por desenho:
            //  - bancos: catálogo COMPE público (código 341 = Itaú é o mesmo para todos);
            //  - personal_access_tokens: token da IDENTIDADE (tokenable = users), que é
            //    compartilhada pela suíte. Quem revoga é o UserService (removeMembership/
            //    changeStatus/deleteUser), não o off-boarding do tenant.
            'exclude_tables' => ['bancos', 'personal_access_tokens'],
            // v0.3.0 — desambiguação do off-boarding: tabela com DUAS FKs para
            // tabelas escopadas exige a aresta declarada, senão o purge recusa.
            // O Compras não tem nenhuma ambígua hoje (o doctor confere).
            'edges' => [],
            'export_redact' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
        ],
    ],

    /*
     * Papéis (slug) cujos usuários são obrigados a ter 2FA, além do admin.
     * Compras: compradora sênior e financeiro.
     */
    'mandatory_2fa_roles' => ['compras', 'financeiro'],

    /*
     * Rota inicial padrão pós-login.
     */
    'home_route' => 'dashboard',

    /*
     * Configurações da página de login (helix-foundation).
     */
    'login_title_top' => 'Gestão Inteligente de',
    'login_title_highlight' => 'Compras & Estoque',
    'login_description' => 'Requisições, cotações, pedidos, recebimento e gestão de estoque integrados com aprovações, fluxos e auditoria completa.',
    'login_features' => [
        'Requisições com triagem automática',
        'Cotações e seleção de fornecedores',
        'Gestão de pedidos de compra',
        'Recebimento e estoque em tempo real',
        'Aprovações por alçada',
        'Relatórios financeiros e operacionais',
    ],
    'login_flow_label' => 'Ciclo de Compras',
    'login_flow_steps' => ['Requisição', 'Cotação', 'Pedido', 'Recebimento', 'Estoque'],
    'login_cards' => [
        ['icon' => 'document', 'valor' => '1.248', 'label' => 'Requisições/mês', 'accent' => 'text-blue-300'],
        ['icon' => 'trending-up', 'valor' => '98,2%', 'label' => 'Taxa de aprovação', 'accent' => 'text-cyan-300'],
        ['icon' => 'cube', 'valor' => '87', 'label' => 'Fornecedores ativos', 'accent' => 'text-blue-300'],
        ['icon' => 'check-badge', 'valor' => '100%', 'label' => 'Conformidade fiscal', 'accent' => 'text-cyan-300'],
    ],
    'login_subtitle' => 'Entre para continuar para o painel de Compras & Estoque.',
    'login_support' => 'Fale com o gestor de Compras',
];
