const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("12. Financeiro"),
  L.P("O módulo Financeiro cuida do pós-pedido: registra e agenda pagamentos aos fornecedores e concilia os lançamentos com o extrato bancário. É uma área de risco — toda ação aqui movimenta dinheiro."),
  L.callout("aviso", "Atenção redobrada: registrar, agendar ou conciliar pagamentos afeta valores reais. Confira fornecedor, pedido e valor antes de confirmar. Use os checklists do capítulo 18."),

  L.H2("12.1 Pagamentos"),
  L.P("A tela de Pagamentos lista os pagamentos por situação (a pagar, agendado, pago, conciliado) e permite registrar o pagamento de um pedido."),
  ...L.figura("34-fin-pagamentos.png", "Lista de pagamentos."),
  L.H3("Registrar um pagamento"),
  L.numbered("Localize o pedido/pagamento a quitar."),
  L.numbered("Informe o método de pagamento e os dados (valor, data)."),
  L.numbered("Confirme. O pagamento passa a Pago."),
  L.fieldTable("①", "Método de pagamento", [
    ["Tipo", "Lista de seleção"],
    ["Obrigatório", "Sim"],
    ["Origem", "Métodos de pagamento do sistema"],
    ["Impacto", "Classifica o pagamento e orienta a conciliação."],
  ]),
  L.spacer(),
  L.fieldTable("②", "Valor / Data", [
    ["Tipo", "Número / data"],
    ["Obrigatório", "Sim"],
    ["Regra de negócio", "O valor é derivado do pedido; confira antes de confirmar."],
  ]),

  L.H2("12.2 Agendamentos"),
  L.P("Pagamentos podem ser agendados para uma data futura, organizando o fluxo de caixa. A tela de Agendamentos concentra os pagamentos programados."),
  ...L.figura("36-fin-agendamentos.png", "Agendamentos de pagamento."),
  L.table(
    ["Ação", "Efeito"],
    [
      ["Agendar", "Programa o pagamento para a data definida (status Agendado)."],
      ["Cancelar", "Cancela um pagamento registrado/agendado, conforme as regras de status."],
    ],
    [1800, 7560],
  ),
  L.callout("atencao", "Cancelar um pagamento já efetuado é uma operação sensível e pode exigir tratativa contábil. Use apenas com autorização."),

  L.H2("12.3 Reconciliação bancária"),
  L.P("A reconciliação confronta os pagamentos do sistema com o extrato do banco, identificando o que já foi compensado. A importação do extrato é feita por arquivo CSV."),
  ...L.figura("35-fin-reconciliacao.png", "Reconciliação bancária."),
  L.H3("Como reconciliar"),
  L.numbered("Importe o extrato bancário (CSV) na tela de Reconciliação."),
  L.numbered("O sistema processa as linhas e tenta casar cada lançamento com um pagamento."),
  L.numbered("Revise os itens conciliados e os pendentes."),
  L.numbered("Confirme a reconciliação; os pagamentos casados passam a Conciliado."),
  L.callout("info", "O total conciliado e o número de itens conciliados ficam visíveis na tela, dando uma medida rápida da saúde da conciliação do período."),
  L.callout("bp", "Reconcilie em ciclos curtos (idealmente diários ou semanais): quanto mais perto do lançamento, mais fácil identificar divergências antes que se acumulem."),

  L.H2("12.4 Erros comuns"),
  L.table(
    ["Situação", "Causa / Solução"],
    [
      ["Pagamento não aparece para registrar", "O pedido ainda não está em condição de pagamento (não emitido/recebido). Verifique o status do pedido."],
      ["Linha do extrato não concilia", "Valor, data ou fornecedor divergem do pagamento. Ajuste o pagamento ou trate manualmente a linha."],
      ["CSV não importa", "Formato do arquivo incompatível. Use o layout de extrato esperado pelo sistema."],
    ],
    [3200, 6160],
  ),
];
