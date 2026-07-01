const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("6. Triagem e Via Expressa"),
  L.P("Toda requisição submetida cai em uma fila única, gerida pela Compradora Sênior. A triagem é o momento em que ela decide o que fazer com cada requisição. Este capítulo cobre a Fila de Triagem e o caminho acelerado por preço homologado — a Via Expressa."),

  L.table(
    ["Atributo", "Descrição"],
    [
      ["Tela", "Fila de Triagem"],
      ["Objetivo", "Analisar as requisições recebidas e encaminhá-las (cotação, estoque, via expressa ou devolução)."],
      ["Quem utiliza", "Compradora Sênior."],
      ["Quando utilizar", "Diariamente, para processar a fila por urgência e idade."],
    ],
    [2300, 7060],
  ),

  L.H2("6.1 Fila de Triagem"),
  L.P("A fila lista as requisições em Aguardando triagem e Em triagem, de toda a rede, ordenadas para priorizar atrasadas e urgentes. Cada linha traz código, solicitante, unidade, itens/valor, status e data de submissão."),
  ...L.figura("12-compradora-triagem.png", "Fila de Triagem com uma requisição elegível à Via Expressa (badge ⚡ Expressa)."),

  L.H3("Ações disponíveis por requisição"),
  L.table(
    ["Botão", "Quando aparece", "O que faz"],
    [
      ["Ver", "Sempre", "Abre o detalhe completo da requisição."],
      ["Iniciar", "Status Aguardando triagem", "Marca a requisição como Em triagem (a Compradora assumiu a análise)."],
      ["Cotação", "Status Em triagem", "Envia a requisição para a etapa de cotação."],
      ["Devolver", "Status Em triagem", "Retorna ao solicitante para ajuste; exige motivo (mín. 5 caracteres)."],
      ["⚡ Via Expressa", "Requisição 100% homologada", "Gera automaticamente a cotação a partir dos preços homologados e segue direto para aprovação."],
      ["Atender do Estoque", "Todos os itens com saldo na unidade", "Conclui a requisição baixando direto do estoque, sem compra."],
    ],
    [2000, 2600, 4760],
  ),
  L.callout("info", "A Compradora pode agrupar requisições semelhantes em um único pedido ao mesmo fornecedor, ganhando escala na negociação. O agrupamento ocorre na geração do pedido (cap. 9)."),

  L.H2("6.2 Via Expressa"),
  L.P("A Via Expressa é o caminho acelerado para itens de catálogo com preço já homologado. Quando todos os itens de uma requisição têm preço homologado válido do mesmo fornecedor, a requisição é marcada como Expressa (badge ⚡) e pode ser atendida em um clique."),

  L.H3("O que a Via Expressa faz — e o que NÃO faz"),
  L.table(
    ["Faz", "Não faz"],
    [
      ["Dispensa a cotação ad-hoc: o preço homologado vale como evidência de preço.", "Não dispensa a aprovação: a alçada por valor continua obrigatória."],
      ["Gera automaticamente uma cotação vencedora a partir dos preços homologados.", "Não encurta a cadeia de alçada: uma compra de alto valor ainda exige Diretor/CEO."],
      ["Leva a requisição direto para Aguardando aprovação.", "Não altera os preços: usa exatamente o valor homologado vigente."],
    ],
    [4680, 4680],
  ),
  L.callout("bp", "A Via Expressa é o principal mecanismo de escala da operação: padroniza a cauda longa de compras repetitivas e de baixo valor (parafusos, EPI, material de limpeza), tirando a Compradora da caça por 3 orçamentos."),

  L.H3("Condições de elegibilidade"),
  L.P("Uma requisição é elegível à Via Expressa quando, no momento do atendimento:"),
  L.bullet("Todos os itens são de catálogo (nenhum item avulso)."),
  L.bullet("Cada item possui um preço homologado válido na data (dentro da janela de validade)."),
  L.bullet("Todos os itens resolvem para o mesmo fornecedor."),
  L.callout("atencao", "Se qualquer item for avulso, estiver sem preço homologado válido, ou os itens resolverem para fornecedores diferentes, a requisição NÃO é expressa e segue o fluxo normal de cotação. A elegibilidade é reavaliada no clique — uma homologação vencida desde a submissão invalida o atalho."),

  L.H3("Processo: atender pela Via Expressa"),
  L.numbered("Na Fila de Triagem, localize a requisição com o badge ⚡ Expressa."),
  L.numbered("Clique no botão ⚡ Via Expressa."),
  L.numbered("Confirme a ação. O sistema gera a cotação homologada vencedora e inicia a aprovação."),
  L.numbered("A requisição passa a Aguardando aprovação e segue a alçada normalmente."),
  L.callout("dica", "Para que requisições apareçam como Expressas, o Administrador precisa manter preços homologados atualizados no catálogo (cap. 12). Preços vencidos são desativados automaticamente todos os dias."),

  L.H2("6.3 Devolução ao solicitante"),
  L.P("Se a requisição estiver incompleta ou incorreta, a Compradora pode devolvê-la. A devolução exige um motivo, que fica registrado e visível ao solicitante, que então ajusta e reenvia."),
  L.callout("obs", "A requisição devolvida volta a permitir edição pelo solicitante. Após o reenvio, ela retorna à fila de triagem."),
];
