const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("7. Cotação e Mapa Comparativo"),
  L.P("Quando a requisição não segue pela Via Expressa nem é atendida do estoque, ela entra em cotação. A Compradora registra as propostas dos fornecedores, compara em um mapa por item e escolhe a vencedora antes de liberar a aprovação."),

  L.table(
    ["Atributo", "Descrição"],
    [
      ["Telas", "Cotações (lista) e Mapa de Cotação (comparativo)"],
      ["Objetivo", "Coletar e comparar propostas; definir a cotação vencedora."],
      ["Quem utiliza", "Compradora Sênior."],
      ["Quando utilizar", "Para toda requisição em cotação que não seja expressa nem atendida do estoque."],
    ],
    [2300, 7060],
  ),

  L.H2("7.1 Lista de cotações"),
  L.P("A tela de Cotações concentra as requisições em fase de cotação e o andamento de cada uma (quantas propostas registradas, qual a vencedora)."),
  ...L.figura("13-compradora-cotacoes.png", "Tela de gestão de cotações."),

  L.H2("7.2 Registrar uma cotação"),
  L.P("Para cada fornecedor consultado, registra-se uma cotação. Os dados de uma cotação são:"),
  L.fieldTable("①", "Fornecedor", [
    ["Tipo", "Lista de seleção"],
    ["Obrigatório", "Sim"],
    ["Validação", "O fornecedor deve estar homologado (qualificado) e ativo — fornecedor não homologado é recusado."],
    ["Impacto", "O fornecedor da cotação vencedora será o do pedido de compra."],
  ]),
  L.spacer(),
  L.fieldTable("②", "Valor / Prazo / Validade da proposta", [
    ["Tipo", "Número / dias / data"],
    ["Obrigatório", "Valor é obrigatório para a cotação contar no mínimo exigido."],
    ["Regra de negócio", "Apenas cotações com valor confirmado contam para o número mínimo da faixa de alçada."],
    ["Observação", "Cotações podem ser sugeridas automaticamente a partir de respostas de e-mail do fornecedor; a Compradora confirma o valor oficial."],
  ]),
  L.callout("info", "O sistema também suporta a cotação por item: cada item da requisição pode ter um preço unitário por fornecedor, e o total da cotação é a soma das linhas."),

  L.H2("7.3 Mapa de Cotação"),
  L.P("O Mapa de Cotação é o comparativo: coloca lado a lado, por item, os preços de cada fornecedor, destacando o melhor preço. É a ferramenta de decisão da Compradora."),
  L.callout("dica", "Use o mapa para decidir entre menor preço total e melhor preço item a item. Às vezes vale dividir o pedido entre fornecedores; em outras, consolidar em um só compensa pelo prazo ou pela escala."),

  L.H2("7.4 Definir a vencedora e concluir a cotação"),
  L.P("Concluída a comparação, a Compradora marca uma cotação como vencedora e conclui a etapa. O sistema só permite concluir quando:"),
  L.bullet([b("Número mínimo de cotações "), run("com valor confirmado foi atingido, conforme a faixa de alçada (ex.: 2, 3...). Em compras emergenciais e via expressa, basta 1.")]),
  L.bullet([b("Exatamente uma cotação "), run("está marcada como vencedora.")]),
  L.P("Atendidas as condições, a requisição passa a Cotação concluída e o sistema inicia automaticamente a aprovação por alçada."),
  L.callout("aviso", "Mensagens possíveis ao concluir: “São necessárias ao menos N cotação(ões) com valor confirmado” (faltam propostas) e “É necessário marcar exatamente uma cotação como vencedora”. Resolva o ponto indicado e tente novamente."),

  L.H2("7.5 Regras e exceções"),
  L.table(
    ["Regra", "Detalhe"],
    [
      ["Cotação obrigatória", "Toda compra exige cotação (exceto a Via Expressa, em que o preço homologado faz esse papel)."],
      ["Mínimo por faixa", "O número mínimo de cotações é definido pela faixa de alçada do valor total."],
      ["Fornecedor qualificado", "Só fornecedores homologados e ativos podem ser cotados."],
      ["Emergencial", "Exige no mínimo 1 cotação + justificativa + aprovação do Diretor, independentemente do valor."],
    ],
    [2600, 6760],
  ),
];
