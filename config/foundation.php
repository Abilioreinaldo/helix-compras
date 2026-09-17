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
            // v0.4.0 — colunas sensíveis POR TABELA omitidas do export do tenant, além
            // do piso da fundação (nome `*_password|*_secret|*_token|*_private_key|
            // *_api_key|senha` e cast encrypted*/hashed). O único segredo em coluna do
            // Compras hoje é `cotacoes.email_token`, já coberto pelo piso por nome;
            // tabela nova com certificado/segredo fora desse padrão entra aqui.
            'export_redact_columns' => [],
            // v0.5.0 — diretórios de models extras varridos atrás de cast encrypted*/hashed.
            // Só amplia: app/Models e as models da fundação são varridos sempre, e todas as
            // models do Compras moram em app/Models.
            'model_paths' => [],
        ],
    ],

    /*
     * Papéis (slug) cujos usuários são obrigados a ter 2FA, além do admin.
     * Compras: compradora sênior e financeiro.
     */
    'mandatory_2fa_roles' => ['compras', 'financeiro'],

    /*
     * v0.7.0 (decisão 13) — CANAIS DE COMUNICAÇÃO.
     *
     * ATENÇÃO: o mergeConfigFrom é RASO. Declarar `channels` aqui SUBSTITUI o bloco
     * inteiro do pacote — por isso todas as subchaves estão replicadas, mesmo as que o
     * Compras não usa hoje. Tirar uma daqui não "herda o default": deixa a chave nula.
     *
     * O que muda no Compras: a SOLICITAÇÃO DE COTAÇÃO ao fornecedor fala em nome do
     * cliente (é a empresa dele pedindo preço), então sai pelo canal DELE
     * (CommercialMessenger) — nunca pelo remetente da suíte, que é só para convite,
     * link de senha e troca de e-mail. Sem canal ativo, o envio falha FECHADO.
     */
    'channels' => [
        // Mailer de SISTEMA — declarado em config/mail.php (mailers.system).
        'system_mailer' => env('HELIX_SYSTEM_MAILER', 'system'),
        'system_from' => [
            'address' => env('HELIX_SYSTEM_MAIL_FROM_ADDRESS'),
            'name' => env('HELIX_SYSTEM_MAIL_FROM_NAME', 'HELIX'),
        ],

        /*
         * Features cuja operação fala com o CLIENTE FINAL do tenant. O `helix:doctor`
         * avisa os tenants que as têm ligadas e não têm canal ativo — que é exatamente
         * a pendência do Compras hoje: o e-mail ao fornecedor precisa do domínio de
         * envio da empresa (SPF/DKIM/DMARC dela) cadastrado em /admin/canais.
         */
        'commercial_features' => ['compras'],

        'reserved_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('HELIX_CHANNEL_RESERVED_DOMAINS', ''))))),
        'rate_per_minute' => (int) env('HELIX_CHANNEL_RATE_PER_MINUTE', 60),
        'evolution' => [
            // O Compras não manda WhatsApp: a allowlist fica vazia (fail-closed).
            'base_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('HELIX_EVOLUTION_BASE_URLS', ''))))),
            'timeout' => (int) env('HELIX_EVOLUTION_TIMEOUT', 15),
        ],
        'email' => [
            // Fail-closed: vazio = nenhum host SMTP aceito no cadastro do canal.
            'smtp_allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('HELIX_CHANNEL_SMTP_ALLOWED_HOSTS', ''))))),
            'smtp_ports' => [465, 587, 2525],
        ],
        'inbound' => [
            'tolerance_seconds' => (int) env('HELIX_CHANNEL_INBOUND_TOLERANCE', 300),
            'rate_per_minute' => (int) env('HELIX_CHANNEL_INBOUND_RATE', 120),
        ],
        'route' => 'admin.canais',
    ],

    /*
     * v0.7.0 (4ª auditoria, FUNDACAO-2) — raiz dos LINKS de e-mail.
     *
     * `route()` monta a URL com o Host da requisição: um POST com um Host forjado na
     * ação que dispara o link fazia o e-mail legítimo levar o token para o atacante.
     * A raiz vem daqui (vazio = APP_URL, que o doctor exige https em produção).
     */
    'links' => [
        'base_url' => env('HELIX_LINK_BASE_URL', ''),
        'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('HELIX_LINK_ALLOWED_ORIGINS', ''))))),
    ],

    /*
     * v0.7.0 (4ª auditoria, FUNDACAO-6) — o app binda os contratos de step-up?
     *
     * DECLARADO FALSE, com motivo: o Compras é app de NEGÓCIO e não governa plataforma
     * (provisionar tenant, entitlement, billing e status de tenant moram no helix-admin,
     * que binda os dois contratos). O que sobra aqui é o RBAC da própria empresa, em
     * /admin/papeis — e, desde a v0.7.0, `RbacService::guardWrite` já exige AUTORIDADE
     * (plataforma declarada, superadmin ou admin daquele tenant) mesmo sem guard bindado,
     * que era o furo real do achado. O Compras não tem reautenticação/step-up: bindar um
     * contrato com implementação vazia seria pior — daria ao doctor um verde que não
     * corresponde a nada. Se um dia houver step-up aqui, esta linha some.
     */
    'privileged_guard_required' => false,

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
