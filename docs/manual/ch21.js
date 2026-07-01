const L = require("./lib");

module.exports = () => [
  L.H1("21. Checklists Operacionais"),
  L.P("Listas de verificação para as ações de maior impacto. Use-as como rotina — especialmente nas áreas de aprovação, pedido e financeiro."),

  L.H2("21.1 Antes de submeter uma requisição"),
  L.bullet("Unidade e centro de custo corretos."),
  L.bullet("Itens de catálogo sempre que possível (habilitam estoque e via expressa)."),
  L.bullet("Quantidades e valores estimados conferidos."),
  L.bullet("Obra selecionada quando o gasto for de obra (atenção à verba)."),
  L.bullet("Justificativa preenchida se for emergencial."),

  L.H2("21.2 Antes de aprovar"),
  L.bullet("Confira o valor total e a faixa de alçada da etapa atual."),
  L.bullet("Verifique a cotação vencedora e se o preço faz sentido frente ao mapa."),
  L.bullet("Revise os itens: algum deve ser rejeitado (decisão por linha)?"),
  L.bullet("Para cada item rejeitado, registre um motivo claro."),
  L.bullet("Confirme que você não é o solicitante e que tem o nível exigido."),

  L.H2("21.3 Antes de emitir o pedido"),
  L.bullet("Fornecedor correto (o da cotação vencedora)."),
  L.bullet("Itens, quantidades e destinos conferidos (itens rejeitados não entram)."),
  L.bullet("Valores unitários finais conforme a negociação."),
  L.bullet("Gere e revise o PDF antes de enviar ao fornecedor."),

  L.H2("21.4 Antes de receber a mercadoria"),
  L.bullet("Confirme que é o pedido certo e a unidade de destino é a sua."),
  L.bullet("Confira item a item a quantidade recebida contra a pedida."),
  L.bullet("Vincule a nota fiscal / documento da entrega."),
  L.bullet("Para itens com lote, informe lote e validade."),
  L.bullet("Em recebimento parcial, registre a ressalva do saldo."),

  L.H2("21.5 Antes de pagar / conciliar"),
  L.bullet("Fornecedor, pedido e valor conferem."),
  L.bullet("Método e data de pagamento corretos."),
  L.bullet("Na reconciliação, cada linha do extrato casa com um pagamento."),
  L.bullet("Divergências tratadas antes de fechar o período."),

  L.H2("21.6 Antes de cancelar (pedido / pagamento)"),
  L.bullet("Confirme o impacto: requisições e estoque vinculados."),
  L.bullet("Pedido já enviado ao fornecedor? Alinhe com a área antes."),
  L.bullet("Pagamento já efetuado? Pode exigir tratativa contábil."),
  L.bullet("Registre o motivo do cancelamento."),

  L.callout("bp", "Transforme estes checklists em hábito da equipe. A maior parte dos erros e retrabalhos em compras vem de pular uma destas verificações simples."),
];
