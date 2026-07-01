const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("2. Conceitos e Arquitetura"),
  L.P("Este capítulo apresenta os conceitos que se repetem em todas as telas. Entendê-los uma vez evita dúvidas no restante do manual."),

  L.H2("2.1 Perfis de usuário"),
  L.P("O sistema trabalha com seis perfis. Um mesmo usuário pode ter mais de um perfil e estar vinculado a uma ou mais unidades. O perfil determina o que cada pessoa vê e pode fazer."),
  L.table(
    ["Perfil", "Responsabilidade principal"],
    [
      ["Solicitante", "Abre e acompanha requisições da(s) sua(s) unidade(s)."],
      ["Compradora Sênior", "Recebe todas as requisições aprovadas para triagem, faz cotações, negocia e emite os pedidos. Papel centralizado e único."],
      ["Aprovador", "Aprova ou reprova requisições conforme sua alçada (Gestor, Diretor ou CEO). Não pode aprovar a própria requisição."],
      ["Almoxarife", "Confere recebimentos, dá entrada/saída no estoque e realiza inventário."],
      ["Financeiro", "Registra e agenda pagamentos e faz a reconciliação bancária."],
      ["Administrador", "Mantém cadastros (catálogo, unidades, usuários, fornecedores, alçadas, centros de custo) e acessa relatórios."],
    ],
    [2300, 7060],
  ),
  L.callout("info", "O capítulo 4 traz a matriz completa de permissões (o que cada perfil vê, altera, aprova e não pode fazer)."),

  L.H2("2.2 Entidades-chave"),
  L.P("Os termos abaixo aparecem em formulários, listas e relatórios. O Glossário (capítulo 19) traz a lista completa; aqui estão os conceitos estruturais."),
  L.table(
    ["Conceito", "Significado no sistema"],
    [
      ["Unidade", "Local da rede que consome e compra: posto, obra, cervejaria, central ou imobiliária. Toda requisição pertence a uma unidade. Obras têm ciclo de vida (ativa/encerrada) e podem ter verba/orçamento."],
      ["Centro de Custo", "Classificação contábil do gasto, vinculada a uma unidade. Obrigatório na requisição."],
      ["Obra", "Tipo especial de unidade (construtora). Todo gasto é amarrado à obra; há relatório de custo acumulado e controle de verba."],
      ["Fornecedor", "Empresa que fornece itens ou serviços. Possui status ativo/inativo e marca de homologado (qualificado para comprar)."],
      ["Item de Catálogo", "Item padronizado (código, descrição, unidade de medida, categoria). A requisição pode usar item de catálogo ou item avulso (texto livre)."],
      ["Preço Homologado", "Preço de um item de catálogo junto a um fornecedor, com validade. Habilita a Via Expressa."],
      ["Requisição", "Pedido de compra interno aberto pelo solicitante, com itens, centro de custo e justificativa."],
      ["Cotação", "Proposta de um fornecedor para os itens de uma requisição (valor, prazo, validade). Uma é marcada como vencedora."],
      ["Faixa de Alçada", "Regra que, pelo valor total, define o número mínimo de cotações e as etapas de aprovação exigidas."],
      ["Pedido de Compra (PC)", "Documento emitido ao fornecedor a partir de requisições aprovadas. Numeração sequencial."],
      ["Recebimento", "Conferência da entrega contra o pedido. Pode ser total ou parcial; gera entrada em estoque."],
      ["Saldo de Estoque", "Quantidade disponível de um item por unidade. Nunca fica negativo."],
      ["Lote / FEFO", "Para itens com controle de lote (ex.: insumos da cervejaria), o saldo é por lote e validade; a saída segue FEFO (vence primeiro, sai primeiro)."],
    ],
    [2300, 7060],
  ),

  L.H2("2.3 Ciclo de vida da requisição"),
  L.P("A requisição percorre uma sequência controlada de estados (status). O sistema só permite as transições válidas — não é possível, por exemplo, aprovar uma requisição que ainda não teve a cotação concluída."),
  L.table(
    ["Status", "Significado"],
    [
      ["Rascunho", "Em edição pelo solicitante; ainda não submetida."],
      ["Aguardando triagem", "Submetida; na fila única da Compradora."],
      ["Em triagem", "A Compradora iniciou a análise."],
      ["Devolvida", "Retornada ao solicitante para ajuste (com motivo)."],
      ["Em cotação", "Em coleta/registro de cotações."],
      ["Cotação concluída", "Mínimo de cotações atingido e vencedora definida."],
      ["Aguardando aprovação", "Em uma das etapas de alçada."],
      ["Aprovada", "Aprovada por todas as etapas; liberada para virar pedido."],
      ["Reprovada", "Reprovada por um aprovador; retorna ao fluxo para nova rodada."],
      ["Em compra", "Vinculada a um pedido de compra emitido."],
      ["Recebida", "Mercadoria recebida pelo almoxarife."],
      ["Concluída", "Processo encerrado."],
      ["Cancelada", "Cancelada pelo solicitante (enquanto permitido) ou pela Compradora."],
    ],
    [2700, 6660],
  ),
  L.callout("atencao", "Status terminais (Concluída, Reprovada, Cancelada) encerram o ciclo. Apenas Rascunho e Devolvida permitem edição da requisição pelo solicitante."),

  L.H2("2.4 Visibilidade por unidade"),
  L.P("O sistema é multiunidade. A regra de visibilidade é:"),
  L.bullet([b("Solicitante e Aprovador "), run("enxergam apenas as requisições e o estoque da(s) sua(s) unidade(s).")]),
  L.bullet([b("Compradora Sênior e Administrador "), run("enxergam a rede inteira (todas as unidades).")]),
  L.callout("obs", "Por isso a Compradora vê uma fila única de triagem com requisições de todas as unidades, enquanto cada gestor só vê o que é da sua área."),

  L.H2("2.5 Auditoria e rastreabilidade"),
  L.P("Toda ação relevante é registrada. Em especial, cada mudança de status da requisição grava um log com: quem executou, data/hora, status anterior e novo, e observação. A rejeição de um item na aprovação também é registrada com o motivo informado."),
  L.callout("bp", "Como a trilha é completa e imutável, oriente a equipe a sempre preencher justificativas claras: elas ficam no histórico e são consultadas em auditorias e disputas."),
];
