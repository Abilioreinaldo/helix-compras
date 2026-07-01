const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

const faq = (pergunta, resposta) => [
  L.P([b("P: "), run(pergunta)]),
  L.P([b("R: "), run(resposta)], { spacing: { after: 200 } }),
];

module.exports = () => [
  L.H1("18. Perguntas Frequentes (FAQ)"),
  L.P("Respostas rápidas para as dúvidas mais comuns. Para mensagens de erro específicas, veja o capítulo 19 (Troubleshooting)."),

  L.H2("18.1 Requisições"),
  ...faq("Não consigo editar minha requisição. Por quê?",
    "Porque ela já foi submetida. Só requisições em Rascunho ou Devolvida podem ser editadas. Se precisar mudar algo, aguarde a devolução pela Compradora ou cancele (enquanto ainda não aprovada) e abra outra."),
  ...faq("Minha requisição sumiu da lista. O que houve?",
    "Ela provavelmente mudou de status (por exemplo, foi para cotação ou aprovação) ou foi concluída/cancelada. Use os filtros e o status para localizá-la; nada é apagado — tudo fica no histórico."),
  ...faq("Por que apareceu o badge ⚡ Expressa na minha requisição?",
    "Porque todos os itens são de catálogo com preço homologado válido do mesmo fornecedor. Isso permite à Compradora atendê-la pela Via Expressa, sem cotação ad-hoc."),
  ...faq("A submissão foi bloqueada por verba. O que fazer?",
    "A obra atingiu 100% do orçamento. Reduza o valor, troque a obra ou peça ao Administrador a revisão da verba."),

  L.H2("18.2 Aprovação"),
  ...faq("Não consigo aprovar uma requisição. Por quê?",
    "Verifique três pontos: (1) você é aprovador do nível da etapa atual? (2) você não é o solicitante dela? (3) ela está em Aguardando aprovação? Qualquer um desses pode bloquear."),
  ...faq("Rejeitei um item, mas a alçada continua exigindo o CEO. É bug?",
    "Não. É a regra anti-fracionamento: a alçada é definida na submissão pelo valor total e não diminui ao rejeitar itens. Isso impede burlar a aprovação removendo itens."),
  ...faq("Posso rejeitar todos os itens de uma vez?",
    "Não. Se a intenção é barrar a compra inteira, use Reprovar. A decisão por linha serve para rejeitar parte dos itens."),

  L.H2("18.3 Compras e Pedidos"),
  ...faq("Por que preciso de 3 cotações se já sei o preço?",
    "Porque a cotação é obrigatória por faixa de valor. Se o item tem preço homologado, use a Via Expressa — nela o preço homologado substitui as cotações."),
  ...faq("Posso comprar de um fornecedor novo?",
    "Só depois de homologá-lo (e mantê-lo ativo). Fornecedor não homologado é recusado na cotação e no preço homologado."),
  ...faq("Meu pedido não aparece para receber. Por quê?",
    "Confirme se o pedido foi emitido e se a unidade de destino é a sua. Apenas pedidos emitidos para a sua unidade entram na sua fila de recebimento."),

  L.H2("18.4 Estoque e Financeiro"),
  ...faq("A saída foi bloqueada por saldo. O que fazer?",
    "Não há saldo suficiente. Confirme o saldo real; se necessário, abra requisição de compra para repor o item."),
  ...faq("Uma linha do extrato não concilia. Por quê?",
    "Valor, data ou fornecedor divergem do pagamento registrado. Ajuste o pagamento ou trate a linha manualmente na reconciliação."),
];
