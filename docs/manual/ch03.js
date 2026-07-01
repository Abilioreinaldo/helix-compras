const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("3. Primeiros Passos"),

  L.H2("3.1 Acesso ao sistema"),
  L.P("O HELIX Compras é um sistema web: roda no navegador, sem instalação. Use a URL fornecida pela sua equipe de TI e um navegador atualizado (Google Chrome, Microsoft Edge ou Firefox). Cada usuário tem credenciais individuais e intransferíveis."),
  L.callout("info", "O acesso e as permissões dependem do seu perfil e da(s) sua(s) unidade(s). Se uma tela ou um menu não aparece para você, é porque o seu perfil não tem acesso a ela."),

  L.H2("3.2 Tela de Login"),
  L.P("A tela de login é a porta de entrada. Informe e-mail e senha e clique em Entrar."),
  ...L.figura("00-login.png", "Tela de Login do HELIX Compras."),
  L.H3("Campos"),
  L.fieldTable("①", "E-mail", [
    ["Tipo", "Texto (e-mail)"],
    ["Obrigatório", "Sim"],
    ["Origem", "Cadastro de usuários (Administração)"],
    ["Validação", "Deve ser um e-mail válido e existente no sistema."],
    ["Exemplo", "compradora@comendador.com.br"],
  ]),
  L.spacer(),
  L.fieldTable("②", "Senha", [
    ["Tipo", "Texto (oculto)"],
    ["Obrigatório", "Sim"],
    ["Validação", "Deve corresponder à senha cadastrada para o e-mail informado."],
    ["Regra", "Após autenticação, o usuário é direcionado ao Dashboard."],
  ]),
  L.spacer(),
  L.fieldTable("③", "Manter conectado", [
    ["Tipo", "Caixa de seleção"],
    ["Obrigatório", "Não"],
    ["Função", "Mantém a sessão ativa por mais tempo neste navegador."],
    ["Boa prática", "Não marque em computadores compartilhados."],
  ]),
  L.H3("Botões"),
  L.table(
    ["Botão", "O que acontece ao clicar"],
    [
      ["Entrar", "Valida as credenciais. Se corretas, abre o Dashboard. Se incorretas, exibe mensagem de erro e permanece na tela."],
      ["Google / Microsoft", "Login social — desabilitado nesta versão (em breve)."],
    ],
    [2200, 7160],
  ),
  L.callout("aviso", "Mensagem comum: “Credenciais inválidas”. Causa: e-mail ou senha incorretos, ou usuário inativo. Solução: confira o e-mail, refaça a senha com atenção a maiúsculas/minúsculas; se persistir, procure o Administrador."),

  L.H2("3.3 Dashboard"),
  L.P("Após o login, o sistema abre o Dashboard — a visão geral de compras e estoque. O conteúdo é adequado ao perfil; a imagem abaixo mostra a visão do Administrador."),
  ...L.figura("01-dashboard.png", "Dashboard — indicadores, pipeline de requisições e atividade recente."),
  L.H3("Indicadores (cartões superiores)"),
  L.table(
    ["Indicador", "O que mostra"],
    [
      ["Requisições abertas", "Total de requisições em andamento no fluxo (não concluídas/canceladas)."],
      ["Aguardando triagem", "Requisições na fila única da Compradora, aguardando início da triagem."],
      ["Aguardando aprovação", "Requisições paradas em alguma etapa de alçada."],
      ["Pedidos emitidos", "Quantidade de pedidos de compra já emitidos."],
      ["Valor em pedidos emitidos", "Soma financeira dos pedidos emitidos."],
      ["Valor em estoque", "Valor total do estoque (saldo × custo) consolidado."],
    ],
    [3000, 6360],
  ),
  L.H3("Painéis inferiores"),
  L.bullet([b("Requisições no pipeline "), run("— distribuição das requisições por status, em barras, para leitura rápida de onde está o volume (ex.: muitas “Em cotação” indicam gargalo na Compradora).")]),
  L.bullet([b("Atividade recente "), run("— últimas requisições movimentadas, com código, unidade, data e status atual.")]),
  L.callout("dica", "Use o Dashboard como ponto de partida do dia: o cartão “Aguardando aprovação” e o painel de pipeline mostram rapidamente onde a sua atenção é necessária."),

  L.H2("3.4 Navegação e menu lateral"),
  L.P("A navegação fica no menu lateral esquerdo, organizado por grupos. Os grupos e itens visíveis variam conforme o perfil. Os grupos típicos são:"),
  L.table(
    ["Grupo", "Conteúdo"],
    [
      ["Geral", "Dashboard."],
      ["Minhas Requisições / Compras", "Acesso às requisições, itens a repor e fluxo de compra do perfil."],
      ["Relatórios", "Relatórios gerenciais (gasto, estoque, aprovação, obra)."],
      ["Financeiro", "Pagamentos, agendamentos e reconciliação (perfil Financeiro/Admin)."],
      ["Administração", "Cadastros e parâmetros (perfil Administrador)."],
    ],
    [3000, 6360],
  ),
  L.P([run("No topo do menu aparecem o logotipo "), b("HELIX"), run(" e o descritivo "), b("Compras & Estoque"), run(". No rodapé do menu, o nome e o e-mail do usuário logado. No canto superior direito ficam o nome do usuário e o botão "), b("Sair"), run(".")]),

  L.H2("3.5 Encerrar a sessão"),
  L.numbered("Clique no seu nome, no canto superior direito."),
  L.numbered("Clique em Sair."),
  L.numbered("O sistema encerra a sessão e retorna à tela de Login."),
  L.callout("bp", "Sempre encerre a sessão ao terminar, especialmente em computadores compartilhados — a área de Compras lida com dados sensíveis (preços, fornecedores, valores)."),
];
