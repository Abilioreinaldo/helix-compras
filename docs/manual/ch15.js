const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

const regra = (titulo, linhas, certo, errado) => [
  L.H3(titulo),
  L.table(["Aspecto", "Definição"], linhas, [2400, 6960]),
  ...(certo ? [L.callout("dica", [[b("Exemplo correto: "), run(certo)]])] : []),
  ...(errado ? [L.callout("aviso", [[b("Exemplo incorreto: "), run(errado)]])] : []),
  L.spacer(),
];

module.exports = () => [
  L.H1("15. Regras de Negócio"),
  L.P("Este capítulo consolida as regras efetivamente implementadas no sistema. Elas são a espinha dorsal do controle de compras: garantem que toda compra seja cotada, aprovada e rastreável. Cada regra traz objetivo, gatilho, responsável, condições, exceções e exemplos."),

  L.callout("info", "As regras abaixo são aplicadas pelo próprio sistema: quando uma condição não é atendida, o sistema exibe a mensagem indicada e interrompe a ação, registrando o evento."),

  L.H2("15.1 Requisição"),
  ...regra("RN-01 — Requisição não editável após submissão", [
    ["Objetivo", "Preservar a integridade do que foi submetido ao fluxo."],
    ["Quando dispara", "Ao tentar editar uma requisição já submetida."],
    ["Quem", "Solicitante."],
    ["Condição", "Só Rascunho e Devolvida permitem edição."],
    ["Exceção", "Requisição devolvida volta a ser editável; pode ser ajustada e reenviada."],
  ],
    "O solicitante percebe um erro antes de submeter e corrige no rascunho.",
    "Tentar alterar a quantidade de uma requisição que já está em cotação."),

  ...regra("RN-02 — Controle de verba da obra", [
    ["Objetivo", "Impedir que uma obra estoure o orçamento."],
    ["Quando dispara", "Na submissão de requisição vinculada a uma obra com verba."],
    ["Condição", "Consumo ≥ 100% bloqueia a submissão; ≥ 80% gera alerta."],
    ["Exceção", "Obras sem verba cadastrada não disparam o controle."],
  ],
    "A R$ 244,60 de uma obra com verba de R$ 1.000.000 passa com folga.",
    "Submeter requisição que levaria a obra a 105% da verba — bloqueada."),

  L.H2("15.2 Cotação"),
  ...regra("RN-03 — Cotação obrigatória e mínimo por faixa", [
    ["Objetivo", "Garantir evidência de preço antes da aprovação."],
    ["Quando dispara", "Ao concluir a cotação."],
    ["Quem", "Compradora Sênior."],
    ["Condição", "Atingir o número mínimo de cotações com valor confirmado da faixa, e ter exatamente uma vencedora."],
    ["Exceção", "Via Expressa (preço homologado faz o papel) e Emergencial (mínimo 1)."],
  ],
    "Faixa exige 3 cotações: a Compradora registra 3 fornecedores e marca a melhor como vencedora.",
    "Tentar concluir com 1 cotação numa faixa que exige 3 — recusado."),

  ...regra("RN-04 — Apenas fornecedor homologado e ativo", [
    ["Objetivo", "Comprar só de fornecedores qualificados."],
    ["Quando dispara", "Ao registrar cotação ou cadastrar preço homologado."],
    ["Condição", "Fornecedor com homologado = true e ativo = true."],
  ],
    "Cotar a Distribuidora Norte (homologada e ativa).",
    "Cotar um fornecedor recém-cadastrado ainda não homologado — recusado."),

  L.H2("15.3 Aprovação e Alçada"),
  ...regra("RN-05 — Roteamento por valor, sem autoaprovação", [
    ["Objetivo", "Exigir o nível de aprovação proporcional ao valor."],
    ["Quando dispara", "Ao iniciar a aprovação (após cotação concluída)."],
    ["Condição", "O valor total casa uma faixa, que materializa as etapas. Não há aprovação automática em nenhuma faixa."],
    ["Exceção", "Emergencial inclui obrigatoriamente a etapa do Diretor."],
  ],
    "Compra de R$ 25.000 percorre Diretor e depois CEO.",
    "Esperar que uma compra pequena seja aprovada sozinha — não existe."),

  ...regra("RN-06 — Solicitante não aprova a própria requisição", [
    ["Objetivo", "Segregação de funções."],
    ["Quando dispara", "Ao aprovar/reprovar."],
    ["Condição", "O aprovador não pode ser o solicitante da requisição."],
  ],
    "Outro gestor da unidade aprova a requisição aberta pelo solicitante.",
    "O próprio solicitante, sendo também aprovador, tenta aprovar a sua requisição — bloqueado."),

  ...regra("RN-07 — Decisão por linha não encurta a alçada (anti-fracionamento)", [
    ["Objetivo", "Impedir burlar a alçada removendo itens para cair em faixa menor."],
    ["Quando dispara", "Ao rejeitar itens durante a aprovação."],
    ["Condição", "A faixa é travada na submissão (valor total). Rejeitar itens reduz custo, mas não remove etapas. Não é possível rejeitar todos os itens."],
  ],
    "Numa compra que exige Diretor + CEO, o Gestor rejeita 1 item; a requisição continua exigindo Diretor + CEO.",
    "Rejeitar itens esperando que a compra passe a precisar só do Gestor — não acontece."),

  L.H2("15.4 Via Expressa"),
  ...regra("RN-08 — Elegibilidade da Via Expressa", [
    ["Objetivo", "Acelerar compras de itens já homologados sem perder controle."],
    ["Quando dispara", "Na submissão (marca) e no atendimento (reavalia)."],
    ["Condição", "Todos os itens de catálogo, todos com preço homologado válido, todos do mesmo fornecedor."],
    ["Exceção", "Qualquer item avulso, sem homologação válida ou de outro fornecedor desqualifica a via expressa."],
  ],
    "Requisição com 2 itens homologados da mesma distribuidora é atendida em 1 clique.",
    "Incluir 1 item avulso na requisição e esperar a via expressa — ela segue o fluxo normal."),

  ...regra("RN-09 — Via Expressa dispensa cotação, nunca aprovação", [
    ["Objetivo", "Manter a aprovação obrigatória mesmo no caminho rápido."],
    ["Condição", "A cotação homologada é gerada automaticamente; a requisição segue a alçada por valor."],
  ],
    "Compra expressa de R$ 25.000 ainda passa por Diretor + CEO.",
    "Achar que a via expressa aprova sozinha — ela só dispensa a cotação ad-hoc."),

  L.H2("15.5 Estoque"),
  ...regra("RN-10 — Saldo nunca negativo e FEFO", [
    ["Objetivo", "Evitar saldo impossível e priorizar validade."],
    ["Quando dispara", "Em saídas e transferências."],
    ["Condição", "Saída sem saldo suficiente é bloqueada. Itens com lote saem por FEFO (vence primeiro, sai primeiro)."],
    ["Exceção", "Lote vencido apenas alerta, não bloqueia."],
  ],
    "Saída de 10 un com saldo de 50 un baixa os lotes de validade mais próxima.",
    "Tentar dar saída de 100 un com saldo de 50 un — bloqueado."),

  L.H2("15.6 Pedido e Recebimento"),
  ...regra("RN-11 — Pedido só de requisições aprovadas, sem itens rejeitados", [
    ["Objetivo", "Comprar apenas o que foi aprovado."],
    ["Condição", "Cada item do pedido vem de requisição aprovada com cotação vencedora do fornecedor. Itens rejeitados não entram."],
  ],
    "Pedido agrupa 3 requisições aprovadas da mesma distribuidora.",
    "Incluir no pedido uma requisição ainda em aprovação — recusado."),

  ...regra("RN-12 — Recebimento parcial com ressalva", [
    ["Objetivo", "Registrar o que chegou sem travar a operação."],
    ["Condição", "Recebido ≤ pedido; o que chega entra no estoque, a diferença fica como pendência/ressalva."],
  ],
    "Pedido de 100 un, chegam 60: entra 60 e fica pendência de 40.",
    "Registrar 120 un recebidas para um pedido de 100 — não permitido."),

  L.H2("15.7 Auditoria"),
  ...regra("RN-13 — Toda mudança de status é registrada", [
    ["Objetivo", "Rastreabilidade total."],
    ["Condição", "Cada transição grava quem, quando, de→para e observação. Rejeição de item grava o motivo."],
  ],
    "O histórico mostra a requisição passando de Em cotação para Cotação concluída, com data e responsável.",
    null),
];
