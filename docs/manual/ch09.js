const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("9. Pedido de Compra"),
  L.P("Aprovada a requisição, a Compradora gera o Pedido de Compra (PC) — o documento formal enviado ao fornecedor. Este capítulo cobre a criação, a emissão e o agrupamento de requisições em um pedido."),

  L.table(
    ["Atributo", "Descrição"],
    [
      ["Telas", "Pedidos de Compra (lista), Formulário/Detalhe do Pedido"],
      ["Objetivo", "Consolidar requisições aprovadas em um pedido ao fornecedor e emiti-lo."],
      ["Quem utiliza", "Compradora Sênior."],
      ["Numeração", "Sequencial: PC-AAAA-NNNN."],
    ],
    [2300, 7060],
  ),

  L.H2("9.1 Lista de pedidos"),
  L.P("A tela reúne os pedidos por status (rascunho, emitido, recebido) e permite abrir, editar, emitir e gerar o PDF."),
  ...L.figura("14-compradora-pedidos.png", "Gestão de Pedidos de Compra."),

  L.H2("9.2 Como o pedido é montado"),
  L.P("O pedido nasce de requisições aprovadas do mesmo fornecedor (o fornecedor da cotação vencedora). A Compradora pode agrupar várias requisições aprovadas em um único pedido, ganhando escala na negociação."),
  L.callout("info", "Cada item do pedido vem de um item de requisição aprovado. Itens rejeitados na decisão por linha (cap. 8) NÃO entram no pedido."),
  L.callout("atencao", "Para entrar em um pedido, a requisição precisa estar Aprovada (ou Em compra) e ter uma cotação vencedora do fornecedor escolhido. Sem isso, o sistema recusa a inclusão."),

  L.H2("9.3 Itens com destinos diferentes"),
  L.P("Um pedido agrupado pode conter itens com unidades de entrega distintas — cada item indica a sua unidade de destino. Isso permite que um único pedido ao fornecedor atenda várias unidades da rede de uma vez."),

  L.H2("9.4 Emitir o pedido"),
  L.numbered("Revise o pedido: fornecedor, itens, quantidades, valores unitários e destinos."),
  L.numbered("Ajuste os valores unitários do pedido conforme a negociação final, se necessário."),
  L.numbered("Emita o pedido. O status passa a Emitido e as requisições vinculadas vão para Em compra."),
  L.numbered("Gere o PDF do pedido para envio ao fornecedor."),
  L.callout("dica", "Confira o PDF antes de enviar: ele é o documento oficial que o fornecedor usará para faturar e entregar. Erros aqui geram divergências no recebimento."),

  L.H2("9.5 Cancelamento de pedido"),
  L.P("Um pedido pode ser cancelado conforme as regras de status. Ao cancelar, as requisições vinculadas retornam ao estado apropriado para nova tratativa."),
  L.callout("aviso", "Cancelar um pedido é uma ação de impacto: afeta as requisições vinculadas e o pipeline de compra. Confirme com a área antes de cancelar um pedido já enviado ao fornecedor."),

  L.H2("9.6 Botões"),
  L.table(
    ["Botão", "Função / efeito"],
    [
      ["Editar", "Abre o pedido em rascunho para ajuste de itens e valores."],
      ["Emitir", "Formaliza o pedido (status Emitido) e move as requisições para Em compra."],
      ["PDF", "Gera o documento do pedido para envio ao fornecedor."],
      ["Cancelar", "Cancela o pedido conforme as regras de status, liberando as requisições."],
    ],
    [1800, 7560],
  ),
];
