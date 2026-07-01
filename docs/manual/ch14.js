const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("14. Relatórios"),
  L.P("Os relatórios dão visibilidade gerencial sobre gasto, estoque, aprovação e obras. Estão disponíveis para a Compradora e o Administrador (alguns também para o Aprovador e o Financeiro)."),

  L.H2("14.1 Catálogo de relatórios"),
  L.table(
    ["Relatório", "O que mostra"],
    [
      ["Gastos por Centro de Custo", "Gasto por centro de custo, no mês/ano."],
      ["Gastos por Fornecedor", "Gasto por fornecedor (e por categoria)."],
      ["Custo por Obra", "Custo acumulado por obra, com curva mensal."],
      ["Posição de Estoque", "Saldo, valor e itens abaixo do mínimo — consolidado e por unidade."],
      ["Consumo por Unidade", "Consumo de material por unidade e centro de custo."],
      ["Comparativo entre Unidades", "Comparação de gasto entre unidades (mês/ano)."],
      ["Tempo de Aprovação", "Tempo médio de aprovação por alçada."],
      ["Pendentes por Aprovador", "Requisições paradas, por aprovador."],
      ["Compras Emergenciais", "Volume e detalhe das compras marcadas como emergenciais."],
      ["Rateio da Central", "Rateio de gastos compartilhados da central entre as unidades."],
    ],
    [3200, 6160],
  ),

  L.H2("14.2 Gastos por Centro de Custo"),
  L.P("Distribui o gasto pelos centros de custo no período, base para o controle orçamentário por área."),
  ...L.figura("40-rel-gastos-cc.png", "Relatório de gastos por centro de custo."),

  L.H2("14.3 Custo por Obra"),
  L.P("Para a construtora: custo acumulado por obra, com a curva mensal. Obrigatório no acompanhamento de obras e no controle de verba."),
  ...L.figura("41-rel-custo-obra.png", "Relatório de custo por obra."),

  L.H2("14.4 Posição de Estoque"),
  L.P("Saldo e valor do estoque, com destaque para itens abaixo do mínimo — apoia a reposição e a valoração do estoque."),
  ...L.figura("42-rel-posicao-estoque.png", "Relatório de posição de estoque."),

  L.H2("14.5 Comparativo entre Unidades"),
  L.P("Compara o gasto entre unidades no período, revelando padrões e oportunidades de negociação em escala."),
  ...L.figura("43-rel-comparativo-unidades.png", "Comparativo de gasto entre unidades."),

  L.H2("14.6 Tempo de Aprovação"),
  L.P("Mede o tempo médio de aprovação por alçada — indicador-chave de agilidade do processo e de gargalos na cadeia de aprovação."),
  ...L.figura("44-rel-tempo-aprovacao.png", "Relatório de tempo de aprovação."),
  L.callout("bp", "Use o Tempo de Aprovação e o Pendentes por Aprovador juntos para encontrar gargalos: o primeiro mostra onde demora; o segundo, quem está com a fila parada."),

  L.callout("dica", "Os relatórios são a base da melhoria contínua das compras: comparam unidades, expõem fornecedores caros e medem a agilidade da aprovação. Reserve um momento semanal para analisá-los."),
];
