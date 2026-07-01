const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("1. Introdução"),

  L.H2("1.1 Sobre este manual"),
  L.P("Este é o Manual Oficial do Usuário do HELIX Compras & Estoque, o sistema de gestão de compras (procurement) da Rede Comendador. O documento descreve, tela a tela, como operar o sistema do início ao fim do processo de compra — da abertura de uma requisição até o recebimento da mercadoria e o pagamento ao fornecedor."),
  L.P("O conteúdo foi produzido a partir da navegação direta no sistema e da leitura das regras efetivamente implementadas. Portanto, o que está aqui descrito corresponde ao comportamento real do produto, e não a um projeto ou intenção."),
  L.P([b("Como usar este manual: "), run("se você é novo no sistema, leia os capítulos 1 a 4 na ordem — eles dão a base conceitual. Em seguida, vá direto ao capítulo do seu perfil. Se você já conhece o sistema e procura uma regra específica, use o Sumário, o índice de Regras de Negócio (capítulo 15) ou o Glossário (capítulo 19).")]),

  L.callout("info", [
    "Público-alvo: Solicitantes, Compradora Sênior, Aprovadores, Almoxarifes, equipe Financeira e Administradores.",
    "Usos previstos: treinamento de novos colaboradores, onboarding, implantação em novas unidades, certificação interna e central de ajuda.",
  ]),

  L.H2("1.2 O que é o HELIX Compras"),
  L.P("O HELIX Compras é um sistema web interno que centraliza 100% das compras da Rede Comendador. A rede é formada por unidades heterogêneas — postos de combustível, obras de construtora (cada obra é uma unidade), cervejaria, central administrativa e imobiliárias — e cada uma tem necessidades de compra diferentes."),
  L.P("Antes do sistema, as compras eram feitas de forma dispersa (WhatsApp, e-mail, telefone), sem cotação obrigatória, sem alçada de aprovação e sem rastreabilidade. O HELIX resolve isso impondo um processo único e auditável: toda compra passa por requisição, cotação e aprovação registradas."),

  L.callout("bp", "Princípio central do produto: nenhuma compra acontece fora do sistema, e nenhuma compra é aprovada sem evidência de preço (cotação) e sem a aprovação da alçada correspondente ao valor."),

  L.H2("1.3 Objetivos e benefícios"),
  L.table(
    ["Objetivo", "Como o sistema entrega"],
    [
      ["Centralizar as compras", "Toda requisição converge para uma única Compradora Sênior, que negocia e emite os pedidos. Ninguém compra fora dela."],
      ["Garantir cotação", "O sistema exige um número mínimo de cotações conforme a faixa de valor antes de liberar a aprovação."],
      ["Controlar alçada", "A aprovação é roteada por valor: quanto maior a compra, mais alto o nível de aprovação exigido (Gestor, Diretor, CEO)."],
      ["Dar visibilidade de gasto", "Relatórios mostram o gasto por centro de custo, por fornecedor, por obra e o comparativo entre unidades."],
      ["Rastrear tudo", "Cada mudança de status registra quem fez, quando e de qual estado para qual — trilha de auditoria completa."],
      ["Controlar estoque", "Entradas e saídas por unidade, saldo nunca negativo, alerta de estoque mínimo e inventário."],
    ],
    [2900, 6460],
  ),

  L.H2("1.4 Escopo funcional"),
  L.P("O sistema é organizado nos seguintes blocos funcionais, todos documentados neste manual:"),
  L.bullet([b("Requisições "), run("— abertura, edição, submissão e acompanhamento de pedidos de compra internos.")]),
  L.bullet([b("Triagem e Cotação "), run("— a Compradora Sênior tria a fila, registra cotações e escolhe a vencedora; inclui a via expressa por preço homologado.")]),
  L.bullet([b("Aprovação por alçada "), run("— roteamento por valor, múltiplas etapas, decisão por linha (aprovar/rejeitar itens individualmente).")]),
  L.bullet([b("Pedido de Compra "), run("— geração, emissão (PDF) e envio ao fornecedor; agrupamento de requisições.")]),
  L.bullet([b("Recebimento "), run("— conferência da entrega (total ou parcial) e entrada automática no estoque.")]),
  L.bullet([b("Estoque "), run("— saldo por unidade, movimentações, controle de lote e validade (FEFO), transferências e inventário.")]),
  L.bullet([b("Financeiro "), run("— pagamentos, agendamentos e reconciliação bancária.")]),
  L.bullet([b("Administração "), run("— catálogo de itens e preços homologados, unidades, usuários, fornecedores, alçadas e centros de custo.")]),
  L.bullet([b("Relatórios "), run("— gasto por centro de custo, por fornecedor, por obra, tempo de aprovação, posição de estoque e comparativos.")]),

  L.H3("Fora de escopo (v1)"),
  L.P("Para alinhar expectativas, os itens abaixo não fazem parte desta versão do sistema:"),
  L.bullet("Integração direta com ERP/contabilidade externos."),
  L.bullet("Portal do fornecedor (autoatendimento do fornecedor)."),
  L.bullet("Aplicativo mobile nativo."),
  L.bullet("Compra de combustível de pista nos postos (segue processo próprio fora do sistema)."),

  L.H2("1.5 Visão geral do processo"),
  L.P("Em alto nível, uma compra percorre o caminho abaixo. Cada etapa é detalhada nos capítulos correspondentes."),
  ...L.fluxo([
    "Requisição (Solicitante)",
    "Triagem (Compradora Sênior)",
    "Cotação e Mapa Comparativo",
    "Aprovação por Alçada",
    "Pedido de Compra (emissão ao fornecedor)",
    "Recebimento (Almoxarife)",
    "Entrada em Estoque",
    "Pagamento (Financeiro)",
    "Conclusão",
  ]),
  L.spacer(),
  L.callout("dica", "Existe um caminho acelerado — a Via Expressa — para itens de catálogo com preço já homologado. Ele dispensa a cotação ad-hoc (mas nunca a aprovação). Veja o capítulo 6."),
];
