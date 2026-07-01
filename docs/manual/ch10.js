const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("10. Recebimento"),
  L.P("Quando a mercadoria chega, o Almoxarife confere a entrega contra o pedido e registra o recebimento. A entrada no estoque é automática. Este capítulo cobre o recebimento total e parcial."),

  L.table(
    ["Atributo", "Descrição"],
    [
      ["Tela", "Recebimentos / Registrar Recebimento"],
      ["Objetivo", "Conferir a entrega contra o pedido e dar entrada no estoque."],
      ["Quem utiliza", "Almoxarife da unidade de destino."],
      ["Quando utilizar", "Na chegada física da mercadoria referente a um pedido emitido."],
    ],
    [2300, 7060],
  ),

  L.H2("10.1 Lista de recebimentos"),
  L.P("A tela lista os pedidos pendentes de recebimento na unidade do almoxarife, com a situação de cada um."),
  ...L.figura("30-almox-recebimentos.png", "Gestão de recebimentos do almoxarife."),

  L.H2("10.2 Registrar o recebimento"),
  L.P("Ao registrar, o almoxarife confere, item a item, a quantidade efetivamente recebida contra a quantidade pedida."),
  L.numbered("Abra o pedido a receber."),
  L.numbered("Para cada item, informe a quantidade recebida (igual ou menor que a pedida)."),
  L.numbered("Vincule a nota fiscal / documento da entrega, quando aplicável."),
  L.numbered("Confirme. O sistema dá entrada automática no estoque da unidade de destino."),

  L.H2("10.3 Recebimento total e parcial"),
  L.table(
    ["Tipo", "O que acontece"],
    [
      ["Total", "Todas as quantidades pedidas foram recebidas. O pedido/itens são marcados como recebidos integralmente."],
      ["Parcial", "Recebeu-se menos do que o pedido. O sistema registra o recebido, dá entrada do que chegou e mantém a pendência do saldo, com ressalva."],
    ],
    [1800, 7560],
  ),
  L.callout("atencao", "Recebimento parcial não bloqueia a operação: o que chegou entra no estoque e a diferença fica registrada como ressalva/pendência. Acompanhe as pendências para cobrar o fornecedor do saldo."),

  L.H2("10.4 Entrada automática no estoque"),
  L.P("Cada item recebido gera uma movimentação de entrada no saldo de estoque da unidade de destino, vinculada ao pedido e à nota. Para itens com controle de lote (cap. 11), informa-se também o lote e a validade na entrada."),
  L.callout("info", "A entrada recalcula o custo médio do item no estoque, base para a valoração do estoque exibida no Dashboard e nos relatórios de posição."),

  L.H2("10.5 Erros comuns"),
  L.table(
    ["Situação", "Causa / Solução"],
    [
      ["Não encontro o pedido para receber", "O pedido não foi emitido, ou a unidade de destino não é a sua. Confirme com a Compradora."],
      ["Quantidade recebida maior que a pedida", "Não permitido. Receba até a quantidade pedida; excedentes exigem tratativa específica com a Compradora."],
      ["Item exige lote e validade", "O item tem controle de lote ligado; informe lote e validade na entrada (cap. 11)."],
    ],
    [3200, 6160],
  ),
];
