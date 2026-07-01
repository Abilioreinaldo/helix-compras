const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("16. Fluxos do Processo"),
  L.P("Este capítulo reúne os fluxos do sistema em diagramas, para visualizar rapidamente o caminho de cada processo e quem atua em cada etapa."),

  L.H2("16.1 Fluxo normal de compra"),
  L.P("O caminho padrão, com cotação e aprovação por alçada."),
  ...L.fluxo([
    "Solicitante abre a requisição",
    "Submissão (define faixa de alçada)",
    "Fila de Triagem (Compradora)",
    "Em cotação — registra propostas",
    "Mapa comparativo + vencedora",
    "Cotação concluída",
    "Aprovação por alçada (Gestor → Diretor → CEO)",
    "Aprovada",
    "Pedido de Compra emitido ao fornecedor",
    "Recebimento (Almoxarife) + entrada em estoque",
    "Pagamento (Financeiro)",
    "Concluída",
  ]),

  L.H2("16.2 Fluxo da Via Expressa"),
  L.P("Para requisições 100% homologadas: a cotação é dispensada, mas a aprovação permanece."),
  ...L.fluxo([
    "Requisição 100% de itens homologados",
    "Submissão (marcada como Expressa)",
    "Triagem: botão Via Expressa (1 clique)",
    "Cotação homologada gerada automaticamente",
    "Aprovação por alçada (obrigatória)",
    "Aprovada → Pedido → Recebimento → Conclusão",
  ]),

  L.H2("16.3 Fluxo de devolução e reprovação"),
  L.P("Caminhos de retorno: a Compradora devolve para ajuste, ou o aprovador reprova."),
  ...L.fluxo([
    "Requisição em triagem / aprovação",
    "Devolvida (Compradora) ou Reprovada (Aprovador)",
    "Retorna ao fluxo (solicitante ajusta / volta à cotação)",
    "Nova rodada",
  ]),
  L.callout("info", "A reprovação avança o ciclo de aprovação e devolve a requisição à cotação para nova rodada, notificando a Compradora. A devolução retorna ao solicitante para ajuste."),

  L.H2("16.4 Fluxo de decisão por linha"),
  L.P("O aprovador rejeita itens específicos sem reprovar a requisição inteira."),
  ...L.fluxo([
    "Aprovador abre o Painel de Aprovação",
    "Clica em Aprovar",
    "Marca os itens a rejeitar + motivo",
    "Confirma: itens não marcados aprovados, marcados rejeitados",
    "Requisição segue (alçada não encurta)",
  ]),

  L.H2("16.5 Fluxo de estoque (entrada e saída)"),
  ...L.fluxo([
    "Recebimento de pedido → Entrada no estoque",
    "Requisição de material → Saída (FEFO se houver lote)",
    "Transferência entre unidades → Saída + Entrada",
    "Inventário → Ajuste (com justificativa)",
  ]),
];
