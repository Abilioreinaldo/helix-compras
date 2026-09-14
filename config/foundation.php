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
