const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("5. Requisições de Compra"),
  L.P("A requisição é o documento que inicia toda compra. Este capítulo cobre como criá-la, os campos do formulário, as validações, o ciclo de vida e as regras associadas."),

  L.table(
    ["Atributo", "Descrição"],
    [
      ["Tela", "Nova Requisição / Editar Requisição"],
      ["Objetivo", "Registrar a necessidade de compra de uma unidade, com itens, centro de custo e justificativa."],
      ["Quem utiliza", "Solicitante (e qualquer perfil que abra requisições)."],
      ["Quando utilizar", "Sempre que houver necessidade de comprar item ou serviço que não será atendido pelo estoque."],
      ["Permissões", "O usuário só cria requisição para unidade(s) à(s) qual(is) está vinculado."],
    ],
    [2300, 7060],
  ),

  L.H2("5.1 Tela de Nova Requisição"),
  L.P("O formulário tem duas áreas: Dados Gerais (cabeçalho da requisição) e Itens da Requisição (o que será comprado). A imagem abaixo identifica os campos."),
  ...L.figura("11-requisicao-nova.png", "Formulário de Nova Requisição — Dados Gerais e Itens."),

  L.H2("5.2 Campos — Dados Gerais"),
  L.fieldTable("①", "Unidade", [
    ["Tipo", "Lista de seleção"],
    ["Obrigatório", "Sim"],
    ["Origem", "Unidades às quais o usuário está vinculado"],
    ["Valor padrão", "Primeira unidade do usuário (ou a informada via atalho)"],
    ["Regra de negócio", "Define a quem pertence a requisição e qual estoque/centro de custo aplica. Obras encerradas não aceitam requisição nova."],
    ["Impacto", "Determina a visibilidade (quem enxerga) e o roteamento de aprovação (aprovadores da unidade)."],
  ]),
  L.spacer(),
  L.fieldTable("②", "Centro de Custo", [
    ["Tipo", "Lista de seleção"],
    ["Obrigatório", "Sim"],
    ["Origem", "Centros de custo ativos da unidade selecionada"],
    ["Validação", "Deve pertencer à unidade escolhida; a lista é recarregada ao trocar a unidade."],
    ["Impacto", "Classifica o gasto — base dos relatórios de gasto por centro de custo."],
  ]),
  L.spacer(),
  L.fieldTable("③", "Obra (opcional)", [
    ["Tipo", "Lista de seleção"],
    ["Obrigatório", "Não"],
    ["Origem", "Obras ativas da unidade"],
    ["Regra de negócio", "Quando informada, todo o gasto é amarrado à obra e consome a verba/orçamento dela."],
    ["Impacto", "Alimenta o relatório de Custo por Obra; se a verba estiver perto/acima do limite, o sistema alerta ou bloqueia a submissão."],
  ]),
  L.spacer(),
  L.fieldTable("④", "Urgente", [
    ["Tipo", "Caixa de seleção"],
    ["Obrigatório", "Não"],
    ["Função", "Sinaliza prioridade na fila de triagem da Compradora."],
    ["Impacto", "Não altera a alçada nem dispensa cotação; apenas prioriza visualmente."],
  ]),
  L.spacer(),
  L.fieldTable("⑤", "Emergencial", [
    ["Tipo", "Caixa de seleção"],
    ["Obrigatório", "Não"],
    ["Regra de negócio", "Compra emergencial NÃO pula cotação: exige no mínimo 1 cotação e justificativa, e inclui obrigatoriamente a aprovação do Diretor."],
    ["Dependência", "Ao marcar, o campo Justificativa da emergência torna-se obrigatório (mínimo de 10 caracteres)."],
  ]),
  L.spacer(),
  L.fieldTable("⑥", "Justificativa da emergência", [
    ["Tipo", "Texto longo"],
    ["Obrigatório", "Apenas quando Emergencial está marcado"],
    ["Validação", "Mínimo de 10 caracteres"],
    ["Exemplo", "“Parada de máquina na linha de envase; reposição imediata do rolamento.”"],
  ]),

  L.H2("5.3 Campos — Itens da Requisição"),
  L.P("Cada linha de item pode ser de catálogo (padronizado) ou avulso (descrição livre). Use o campo Filtrar catálogo de itens para localizar itens rapidamente e o botão + Adicionar item para incluir novas linhas."),
  L.fieldTable("⑦", "Catálogo", [
    ["Tipo", "Lista de seleção (busca server-side)"],
    ["Obrigatório", "Não (escolha entre item de catálogo ou avulso)"],
    ["Origem", "Catálogo de itens ativos"],
    ["Regra de negócio", "Ao escolher um item de catálogo, descrição e unidade são preenchidas automaticamente. Se o item tiver preço homologado válido, o R$ unitário também é preenchido e a requisição fica elegível à Via Expressa."],
    ["Impacto", "Itens de catálogo permitem controle de estoque, estoque mínimo e Via Expressa; itens avulsos não."],
  ]),
  L.spacer(),
  L.fieldTable("⑧", "Descrição", [
    ["Tipo", "Texto"],
    ["Obrigatório", "Sim"],
    ["Origem", "Preenchida pelo catálogo, ou digitada (item avulso)"],
    ["Validação", "Máximo de 255 caracteres; bloqueada para edição quando o item é de catálogo."],
  ]),
  L.spacer(),
  L.fieldTable("⑨", "Qtd / Un / R$ unit.", [
    ["Tipo", "Número / texto / número"],
    ["Obrigatório", "Quantidade: sim. Unidade e R$ unitário: recomendados."],
    ["Validação", "Quantidade maior que zero; valores numéricos."],
    ["Regra de negócio", "Quantidade × R$ unitário compõe o valor total estimado, que define a Faixa de Alçada da requisição."],
    ["Impacto", "O valor total estimado determina quantas cotações e quais aprovadores serão exigidos."],
  ]),

  L.H2("5.4 Botões"),
  L.table(
    ["Botão", "Função", "O que acontece ao clicar"],
    [
      ["Voltar", "Sair sem submeter", "Retorna à lista de requisições. Um rascunho salvo é preservado."],
      ["Salvar rascunho", "Persistir sem enviar", "Salva a requisição em Rascunho; pode ser editada depois. Não entra na fila da Compradora."],
      ["Submeter requisição", "Enviar para o fluxo", "Valida o formulário, calcula o valor total, define a Faixa de Alçada, gera o código (REQ-AAAA-NNNNNN), checa a verba da obra (se houver) e muda o status para Aguardando triagem."],
      ["+ Adicionar item", "Nova linha de item", "Inclui uma linha de item avulso vazia para preenchimento."],
    ],
    [2100, 2300, 4960],
  ),

  L.H2("5.5 Validações na submissão"),
  L.P("Ao submeter, o sistema verifica:"),
  L.bullet("Unidade e Centro de Custo informados."),
  L.bullet("Pelo menos um item, cada um com descrição e quantidade válida."),
  L.bullet("Justificativa presente quando a requisição é Emergencial."),
  L.bullet("Item marcado como de catálogo deve ter um item de catálogo selecionado (ou ser marcado como avulso)."),
  L.bullet("Existência de Faixa de Alçada compatível com o valor total (caso contrário, a submissão é bloqueada e o Administrador deve cadastrar a faixa)."),
  L.bullet("Verba da obra: se o consumo atingir 100%, a submissão é bloqueada; a partir de 80%, é exibido alerta."),
  L.callout("aviso", "Mensagem possível: “Nenhuma alçada configurada para este valor.” Causa: não há Faixa de Alçada cobrindo o valor total. Solução: o Administrador deve cadastrar/ajustar a faixa (cap. 12)."),

  L.H2("5.6 Processo: como criar uma requisição"),
  L.numbered("No menu, acesse Nova Requisição."),
  L.numbered("Selecione a Unidade e o Centro de Custo. Se for compra de obra, selecione a Obra."),
  L.numbered("Marque Urgente e/ou Emergencial se aplicável (emergencial exige justificativa)."),
  L.numbered("Adicione os itens: escolha do catálogo (recomendado) ou descreva o item avulso; informe a quantidade."),
  L.numbered("Revise o valor total estimado (soma de quantidade × R$ unitário)."),
  L.numbered("Clique em Submeter requisição. Confira o código gerado e o status Aguardando triagem."),
  L.callout("dica", "Sempre que possível, use itens de catálogo: além de padronizar, eles habilitam o controle de estoque e a Via Expressa, acelerando a compra."),

  L.H2("5.7 Lista de requisições"),
  L.P("A lista reúne as requisições do usuário, com código, unidade, status e valor, e permite acompanhar o andamento e abrir o detalhe de cada uma."),
  ...L.figura("10-requisicoes-lista.png", "Lista de requisições com status e acompanhamento."),
  L.callout("info", "O status indica exatamente em que ponto do fluxo a requisição está (cap. 2.3). Use-o para saber se a ação seguinte é sua ou de outro perfil."),

  L.H2("5.8 Erros comuns e exceções"),
  L.table(
    ["Situação", "Causa / Solução"],
    [
      ["Não consigo editar a requisição", "Ela já foi submetida. Só Rascunho e Devolvida permitem edição. Se precisar ajustar, cancele (se ainda permitido) e crie outra, ou aguarde a devolução."],
      ["A requisição não aparece para a Compradora", "Ela está em Rascunho. É preciso Submeter para entrar na fila de triagem."],
      ["Submissão bloqueada por verba", "A obra atingiu 100% da verba. Reduza o valor, troque a obra ou solicite revisão de orçamento ao Administrador."],
      ["Item sem preço no catálogo", "O item de catálogo não tem preço homologado válido; informe o R$ unitário estimado manualmente."],
    ],
    [3200, 6160],
  ),
];
