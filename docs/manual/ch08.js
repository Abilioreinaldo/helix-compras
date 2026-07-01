const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("8. Aprovação por Alçada e Decisão por Linha"),
  L.P("Toda compra passa por aprovação. A aprovação é roteada por valor: quanto maior a requisição, mais alto o nível exigido. Este capítulo cobre a fila do aprovador, o painel de decisão e a rejeição de itens individuais (decisão por linha)."),

  L.table(
    ["Atributo", "Descrição"],
    [
      ["Telas", "Fila de Aprovações e Painel de Aprovação"],
      ["Objetivo", "Aprovar ou reprovar requisições conforme a alçada; opcionalmente rejeitar itens específicos."],
      ["Quem utiliza", "Aprovador (Gestor, Diretor ou CEO)."],
      ["Permissões", "O aprovador só decide etapas do seu nível e da(s) sua(s) unidade(s). Não pode aprovar a própria requisição."],
    ],
    [2300, 7060],
  ),

  L.H2("8.1 Como a alçada funciona"),
  L.P("Ao concluir a cotação, o sistema casa o valor total da requisição com uma Faixa de Alçada e materializa as etapas de aprovação dela. As etapas são sequenciais: cada uma precisa ser aprovada para a próxima começar. Uma reprovação encerra a rodada e devolve a requisição ao fluxo."),
  L.table(
    ["Faixa de valor (exemplo)", "Cotações mín.", "Aprovação exigida"],
    [
      ["Até R$ 5.000", "2", "Gestor da área"],
      ["R$ 5.000,01 a R$ 20.000", "3", "Diretor"],
      ["Acima de R$ 20.000", "3", "Diretor + CEO (dupla)"],
    ],
    [4360, 1800, 3200],
  ),
  L.callout("obs", "Os valores das faixas são parametrizáveis pelo Administrador (cap. 12). A tabela acima é um exemplo de configuração típica."),
  L.callout("atencao", "Regra do produto: não existe aprovação automática em nenhuma faixa. Toda compra, sem exceção, precisa de aprovação manual registrada."),

  L.H2("8.2 Fila de Aprovações"),
  L.P("O aprovador vê as requisições que aguardam a sua etapa, com filtros por unidade, faixa de valor e período. Cada linha traz código, solicitante, unidade e quando a aprovação foi iniciada."),
  ...L.figura("16-aprovacoes-fila.png", "Fila de Aprovações — requisições aguardando a etapa do aprovador."),
  L.table(
    ["Elemento", "Função"],
    [
      ["Filtros (Unidade, Faixa de Valor, Período)", "Refinam a lista para encontrar requisições específicas."],
      ["Badge Urgente", "Sinaliza requisições marcadas como urgentes pelo solicitante."],
      ["Revisar", "Abre o Painel de Aprovação da requisição."],
    ],
    [3600, 5760],
  ),

  L.H2("8.3 Painel de Aprovação"),
  L.P("O painel reúne tudo o que o aprovador precisa para decidir: etapa atual, dados gerais, cotações (com a vencedora destacada), itens da requisição e o histórico de aprovações."),
  ...L.figura("17-painel-aprovacao.png", "Painel de Aprovação com itens, cotações e histórico."),
  L.table(
    ["Seção", "Conteúdo"],
    [
      ["Etapa atual", "Mostra o nível exigido (Gestor/Diretor/CEO), o ciclo e a ordem da etapa."],
      ["Dados Gerais", "Unidade, valor estimado, urgência, emergência e justificativa."],
      ["Cotações", "Propostas recebidas; a vencedora aparece destacada."],
      ["Itens da Requisição", "Cada item com quantidade, valor unitário e situação (Aprovado / Rejeitado)."],
      ["Histórico de Aprovações", "Trilha das etapas: nível, status, aprovador, justificativa e data."],
    ],
    [2600, 6760],
  ),

  L.H2("8.4 Decisão por linha (rejeitar itens)"),
  L.P("Em vez de reprovar a requisição inteira por causa de um único item problemático, o aprovador pode aprovar a etapa rejeitando apenas os itens indesejados. Isso elimina o retrabalho de reprovar, corrigir, recotar e reaprovar tudo de novo."),
  ...L.figura("18-modal-decisao-linha.png", "Modal de aprovação com a decisão por linha — marque os itens a rejeitar."),
  L.H3("Como funciona"),
  L.numbered("No Painel de Aprovação, clique em Aprovar."),
  L.numbered("No modal, opcionalmente escreva um comentário."),
  L.numbered("Em “Itens (marque para rejeitar)”, marque os itens que NÃO devem ser comprados."),
  L.numbered("Para cada item marcado, informe o motivo da rejeição (obrigatório)."),
  L.numbered("Clique em Confirmar aprovação. Os itens não marcados são aprovados; os marcados saem da compra, com o motivo registrado."),

  L.callout("aviso", [
    "Anti-fracionamento: rejeitar itens reduz o custo da compra, mas NÃO encurta a cadeia de alçada. Uma compra de alto valor que entrou exigindo Diretor + CEO continua exigindo Diretor + CEO, mesmo que itens sejam rejeitados. Isso impede burlar a alçada removendo itens para cair em uma faixa menor.",
    "Não é possível rejeitar todos os itens. Se a intenção é barrar a compra inteira, use Reprovar.",
  ]),

  L.H2("8.5 Aprovar e reprovar"),
  L.table(
    ["Botão", "O que acontece"],
    [
      ["Aprovar / Confirmar aprovação", "Aprova a etapa atual. Se houver próxima etapa, notifica os aprovadores dela; se for a última, a requisição passa a Aprovada e o solicitante é notificado. Itens marcados são rejeitados no mesmo ato."],
      ["Reprovar", "Reprova a requisição (exige justificativa, mín. 10 caracteres). As etapas pendentes são puladas, o ciclo de aprovação avança e a requisição retorna à cotação para nova rodada; a Compradora é notificada."],
    ],
    [2800, 6560],
  ),

  L.H2("8.6 O que acontece com os itens rejeitados"),
  L.bullet("Saem desta requisição e não entram no pedido de compra."),
  L.bullet("Ficam registrados com o motivo, o aprovador e a data — visíveis no histórico."),
  L.bullet("O solicitante pode abrir uma nova requisição para esses itens, se ainda forem necessários."),
  L.callout("info", "No detalhe da requisição, o valor estimado dos itens aprovados (excluindo rejeitados) fica disponível para conferência, embora a alçada permaneça roteada pelo valor total original."),

  L.H2("8.7 Erros comuns"),
  L.table(
    ["Situação", "Causa / Solução"],
    [
      ["Não consigo aprovar", "Você não é aprovador do nível da etapa atual, ou é o solicitante da requisição, ou ela não está mais Aguardando aprovação. Verifique a etapa atual e o seu nível."],
      ["O botão Aprovar não aparece", "A requisição não está na sua etapa, ou você não tem alçada na unidade dela."],
      ["Rejeitei um item mas o valor da alçada não mudou", "Comportamento esperado: a alçada é travada na submissão (anti-fracionamento)."],
    ],
    [3000, 6360],
  ),
];
