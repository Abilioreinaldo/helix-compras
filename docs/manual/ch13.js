const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("13. Administração"),
  L.P("A Administração mantém os cadastros e parâmetros que sustentam todo o processo de compra. São dados críticos: mudanças aqui afetam o que pode ser comprado, por quem e com qual aprovação. Acesso exclusivo do perfil Administrador."),

  L.H2("13.1 Catálogo de Itens"),
  L.P("O catálogo padroniza os itens disponíveis para requisição. Itens de catálogo habilitam controle de estoque, estoque mínimo e Via Expressa."),
  ...L.figura("20-admin-catalogo.png", "Catálogo de Itens — lista e ações por item."),
  L.H3("Campos do item"),
  L.table(
    ["Campo", "Descrição"],
    [
      ["Descrição", "Nome do item (obrigatório)."],
      ["Código", "Identificador único do item (opcional, mas recomendado)."],
      ["Unidade de medida", "un, cx, kg, L, etc."],
      ["Categoria", "Agrupamento (EPI, ferramentas, limpeza, escritório...)."],
      ["Ativo", "Itens inativos não aparecem para requisição."],
      ["Lote", "Liga/desliga o controle de lote e validade (FEFO) do item."],
    ],
    [2400, 6960],
  ),
  L.H3("Ações por item"),
  L.table(
    ["Botão", "Função"],
    [
      ["Preços", "Abre a gestão de preços homologados do item (ver 13.2)."],
      ["Mínimos", "Define o estoque mínimo do item por unidade."],
      ["Editar", "Altera os dados do item."],
      ["Excluir", "Remove o item (bloqueado se houver saldo de estoque vinculado)."],
    ],
    [1800, 7560],
  ),

  L.H2("13.2 Preços Homologados (Via Expressa)"),
  L.P("É aqui que se cadastram os preços homologados que habilitam a Via Expressa (cap. 6). Cada preço associa um item a um fornecedor, com valor e validade. Abra pelo botão Preços na linha do item."),
  ...L.figura("27-modal-homologacao.png", "Modal de Preços Homologados — cadastro por item."),
  L.H3("Campos do preço homologado"),
  L.fieldTable("①", "Fornecedor", [
    ["Tipo", "Lista de seleção"],
    ["Obrigatório", "Sim"],
    ["Validação", "Apenas fornecedores homologados e ativos podem ser selecionados."],
    ["Impacto", "Será o fornecedor da cotação gerada na Via Expressa."],
  ]),
  L.spacer(),
  L.fieldTable("②", "Preço (R$)", [
    ["Tipo", "Número"],
    ["Obrigatório", "Sim"],
    ["Validação", "Maior que zero."],
    ["Impacto", "Vale como evidência de preço na Via Expressa; preenche o R$ unitário na requisição."],
  ]),
  L.spacer(),
  L.fieldTable("③", "Preferencial (desempate)", [
    ["Tipo", "Caixa de seleção"],
    ["Obrigatório", "Não"],
    ["Regra de negócio", "Quando um item tem preço de mais de um fornecedor, o marcado como preferencial vence o desempate. Apenas um preferencial por item."],
  ]),
  L.spacer(),
  L.fieldTable("④", "Validade início / fim", [
    ["Tipo", "Data"],
    ["Obrigatório", "Sim"],
    ["Validação", "O fim deve ser igual ou posterior ao início."],
    ["Regra de negócio", "Fora da janela de validade, o preço não habilita a Via Expressa. Preços vencidos são desativados automaticamente todos os dias."],
  ]),
  L.callout("bp", "Mantenha os preços homologados atualizados: eles são o motor da Via Expressa. Um catálogo bem homologado tira a Compradora da cotação repetitiva e acelera a operação inteira."),

  L.H2("13.3 Unidades"),
  L.P("Cadastro das unidades da rede (posto, obra, cervejaria, central, imobiliária), com tipo, CNPJ, endereço, gestor e status. Obras têm ciclo de vida (ativa/encerrada) e podem ter verba/orçamento."),
  ...L.figura("21-admin-unidades.png", "Cadastro de unidades."),
  L.callout("atencao", "Obra encerrada não recebe requisição nova, mas mantém o histórico. A verba da obra é controlada na submissão da requisição (cap. 5)."),

  L.H2("13.4 Usuários e perfis"),
  L.P("Cadastro de usuários, com vínculo a uma ou mais unidades e atribuição de perfis. Para aprovadores, define-se o nível de alçada (Gestor, Diretor, CEO)."),
  ...L.figura("22-admin-usuarios.png", "Cadastro de usuários e vínculos."),
  L.callout("aviso", "O vínculo unidade + perfil + nível de alçada determina o que cada pessoa vê e aprova. Erros aqui causam acesso indevido ou bloqueio de aprovação — revise com cuidado."),

  L.H2("13.5 Fornecedores"),
  L.P("Cadastro de fornecedores (razão social, CNPJ, contato, categoria), com status ativo/inativo e a marca de homologado (qualificado). Apenas fornecedores homologados e ativos podem ser cotados e ter preços homologados."),
  ...L.figura("23-admin-fornecedores.png", "Cadastro de fornecedores."),

  L.H2("13.6 Faixas de Alçada"),
  L.P("Parametrização do coração da aprovação. Cada faixa define um intervalo de valor, o número mínimo de cotações e as etapas de aprovação (níveis exigidos, em ordem)."),
  ...L.figura("24-admin-alcadas.png", "Faixas de alçada e suas etapas."),
  L.callout("atencao", "Sem uma faixa cobrindo o valor de uma requisição, a submissão é bloqueada. Garanta que as faixas cubram toda a escala de valores, sem lacunas nem sobreposição."),

  L.H2("13.7 Centros de Custo"),
  L.P("Cadastro dos centros de custo, vinculados às unidades. São obrigatórios na requisição e base dos relatórios de gasto."),
  ...L.figura("25-admin-centros-custo.png", "Cadastro de centros de custo."),
];
