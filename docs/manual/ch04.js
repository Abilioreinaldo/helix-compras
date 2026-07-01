const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("4. Perfis e Permissões"),
  L.P("O perfil define o que cada usuário vê e pode fazer. Este capítulo traz a matriz de permissões e o detalhamento de cada papel. Lembre-se: um usuário pode acumular perfis e pertencer a várias unidades."),

  L.H2("4.1 Matriz de permissões"),
  L.P("Visão consolidada do que cada perfil pode realizar nas principais áreas do sistema. “Próprias unidades” significa apenas as unidades às quais o usuário está vinculado; “Rede” significa todas as unidades."),
  L.table(
    ["Ação", "Solicitante", "Compradora", "Aprovador", "Almoxarife", "Financeiro", "Admin"],
    [
      ["Abrir requisição", "Sim", "—", "—", "—", "—", "—"],
      ["Ver requisições", "Próprias", "Rede", "Próprias", "—", "—", "Rede"],
      ["Triar / cotar", "—", "Sim", "—", "—", "—", "—"],
      ["Aprovar / reprovar", "—", "—", "Por alçada", "—", "—", "—"],
      ["Emitir pedido", "—", "Sim", "—", "—", "—", "—"],
      ["Receber mercadoria", "—", "—", "—", "Sim", "—", "—"],
      ["Movimentar estoque", "—", "—", "—", "Sim", "—", "—"],
      ["Registrar pagamento", "—", "—", "—", "—", "Sim", "—"],
      ["Cadastros / parâmetros", "—", "—", "—", "—", "—", "Sim"],
      ["Relatórios gerenciais", "—", "Sim", "Parcial", "—", "Parcial", "Sim"],
    ],
    [2160, 1320, 1320, 1280, 1280, 1200, 800],
  ),
  L.callout("obs", "A matriz é um resumo operacional. As regras detalhadas de cada ação (incluindo exceções) estão nos capítulos das respectivas funcionalidades e no capítulo 15 (Regras de Negócio)."),

  L.H2("4.2 Solicitante"),
  L.P([b("O que faz: "), run("abre requisições de compra para a(s) sua(s) unidade(s) e acompanha o andamento delas até a conclusão.")]),
  L.bullet([b("Vê: "), run("apenas as requisições da(s) própria(s) unidade(s).")]),
  L.bullet([b("Altera: "), run("requisições próprias em Rascunho ou Devolvida.")]),
  L.bullet([b("Não pode: "), run("cotar, aprovar, emitir pedido ou ver requisições de outras unidades.")]),
  L.callout("atencao", "Após submeter, a requisição não pode mais ser editada — apenas cancelada (enquanto não aprovada) ou ajustada se for Devolvida pela Compradora."),

  L.H2("4.3 Compradora Sênior"),
  L.P([b("O que faz: "), run("é o ponto central das compras. Recebe a fila única com as requisições de toda a rede, faz a triagem, registra cotações, escolhe a vencedora e emite os pedidos.")]),
  L.bullet([b("Vê: "), run("a rede inteira (todas as unidades).")]),
  L.bullet([b("Altera: "), run("status de triagem, cotações, pedidos de compra.")]),
  L.bullet([b("Poderes especiais: "), run("agrupar várias requisições em um pedido único; atender uma requisição direto do estoque; usar a Via Expressa para itens homologados; devolver requisição ao solicitante.")]),
  L.bullet([b("Não pode: "), run("aprovar requisições (isso é da alçada).")]),
  L.callout("bp", "Como tudo passa pela Compradora, esse perfil é o principal gargalo de escala. A Via Expressa (cap. 6) e o agrupamento de pedidos existem justamente para reduzir o trabalho manual repetitivo."),

  L.H2("4.4 Aprovador"),
  L.P([b("O que faz: "), run("aprova ou reprova as requisições que chegam à sua etapa de alçada. Cada aprovador tem um nível — Gestor, Diretor ou CEO.")]),
  L.bullet([b("Vê: "), run("requisições da(s) sua(s) unidade(s) que estão aguardando a sua etapa.")]),
  L.bullet([b("Pode: "), run("aprovar, reprovar (com justificativa) e rejeitar itens individualmente na aprovação (decisão por linha — cap. 8).")]),
  L.bullet([b("Não pode: "), run("aprovar a própria requisição; aprovar uma etapa de nível diferente do seu.")]),
  L.callout("aviso", "Um mesmo aprovador não pode ser solicitante e aprovador da mesma requisição. O sistema bloqueia a autoaprovação."),

  L.H2("4.5 Almoxarife"),
  L.P([b("O que faz: "), run("recebe as mercadorias dos pedidos, confere contra o pedido, dá entrada no estoque e faz a gestão de saldo e inventário da sua unidade.")]),
  L.bullet([b("Vê: "), run("recebimentos e estoque da sua unidade.")]),
  L.bullet([b("Pode: "), run("registrar recebimento total ou parcial; atender requisições de material; ajustar estoque via inventário (com justificativa); transferir entre unidades.")]),
  L.bullet([b("Não pode: "), run("comprar, cotar ou aprovar.")]),

  L.H2("4.6 Financeiro"),
  L.P([b("O que faz: "), run("cuida do pós-pedido financeiro: registra e agenda pagamentos e concilia os lançamentos bancários.")]),
  L.bullet([b("Pode: "), run("registrar pagamento de um pedido, agendar, cancelar e reconciliar via importação de extrato (CSV).")]),
  L.callout("atencao", "A área Financeira movimenta dinheiro. Toda ação aqui é sensível: confira valores, fornecedor e pedido antes de confirmar. Veja os checklists do capítulo 18."),

  L.H2("4.7 Administrador"),
  L.P([b("O que faz: "), run("mantém os cadastros e parâmetros que sustentam todo o processo e acessa os relatórios gerenciais.")]),
  L.bullet([b("Mantém: "), run("catálogo de itens e preços homologados, unidades, usuários e perfis, fornecedores, faixas de alçada e centros de custo.")]),
  L.bullet([b("Vê: "), run("a rede inteira e todos os relatórios.")]),
  L.callout("bp", "Mudanças em alçadas, fornecedores homologados e preços homologados afetam diretamente o que pode ser comprado e por quem. Trate esses cadastros como parâmetros críticos: revise antes de salvar."),
];
