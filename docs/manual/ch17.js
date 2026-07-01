const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

const cenario = (titulo, contexto, passos, resultado) => [
  L.H2(titulo),
  L.callout("info", contexto),
  ...passos.map((p) => L.numbered(p)),
  L.callout("dica", [[b("Resultado esperado: "), run(resultado)]]),
  L.spacer(),
];

module.exports = () => [
  L.H1("17. Casos de Uso e Cenários"),
  L.P("Exemplos reais ponta a ponta, cobrindo os tipos de compra mais comuns da rede. Cada cenário mostra o caminho percorrido e os perfis envolvidos."),

  ...cenario("17.1 Compra recorrente de baixo valor (Via Expressa)",
    "A obra precisa repor 4 botinas de segurança e 10 pares de luvas — itens de catálogo já homologados.",
    [
      "Solicitante abre a requisição na obra, escolhe os itens do catálogo (preço já preenchido) e submete.",
      "Na triagem, a Compradora vê o badge ⚡ Expressa e clica em Via Expressa.",
      "O sistema gera a cotação homologada e envia para aprovação.",
      "O Gestor aprova. A Compradora gera o pedido e o almoxarife recebe.",
    ],
    "Compra concluída em poucos cliques, sem caça a orçamentos, com aprovação registrada."),

  ...cenario("17.2 Compra estratégica de alto valor (Diretor + CEO)",
    "A cervejaria precisa comprar 500 kg de malte — insumo de produção, valor acima de R$ 20.000.",
    [
      "Solicitante abre a requisição na cervejaria, item de catálogo (malte), quantidade 500 kg, com centro de custo de produção.",
      "A Compradora tria, coleta 3 cotações e monta o mapa comparativo; marca a vencedora e conclui.",
      "Pela faixa de valor, a aprovação exige Diretor e depois CEO.",
      "Aprovado por ambos, a Compradora emite o pedido; o malte é recebido com controle de lote e validade (FEFO).",
    ],
    "Compra de alto valor totalmente cotada, com dupla aprovação e rastreio de lote no estoque."),

  ...cenario("17.3 Compra emergencial",
    "Parada de máquina na linha de envase: é preciso um rolamento com urgência.",
    [
      "Solicitante abre a requisição, marca Emergencial e escreve a justificativa da emergência.",
      "A Compradora registra ao menos 1 cotação (mínimo emergencial) e conclui.",
      "A aprovação inclui obrigatoriamente o Diretor, independentemente do valor.",
      "Aprovado, o pedido é emitido e a peça recebida.",
    ],
    "Atendimento rápido sem abrir mão da cotação mínima e da aprovação do Diretor."),

  ...cenario("17.4 Compra com item indesejado (decisão por linha)",
    "Uma requisição traz 3 itens, mas um deles está fora do padrão de compra.",
    [
      "A requisição chega à aprovação com os 3 itens cotados.",
      "No Painel de Aprovação, o aprovador clica em Aprovar e marca o item problemático para rejeitar, com o motivo.",
      "Confirma a aprovação: os 2 itens válidos são aprovados; o terceiro sai da compra, com o motivo registrado.",
    ],
    "Compra segue sem o item indesejado, sem precisar reprovar e reabrir tudo."),

  ...cenario("17.5 Atendimento direto do estoque",
    "O item requisitado já existe em saldo na unidade.",
    [
      "Solicitante abre a requisição com itens de catálogo.",
      "Na triagem, como há saldo de todos os itens, a Compradora usa Atender do Estoque.",
      "O sistema baixa o saldo (FEFO quando houver lote) e conclui a requisição — sem compra.",
    ],
    "Necessidade atendida sem gerar pedido nem custo de compra novo."),

  ...cenario("17.6 Recebimento parcial",
    "O fornecedor entrega menos do que o pedido.",
    [
      "O almoxarife abre o pedido para receber.",
      "Informa a quantidade efetivamente recebida (menor que a pedida).",
      "Confirma: o que chegou entra no estoque; a diferença fica registrada como ressalva/pendência.",
    ],
    "Estoque atualizado com o recebido e pendência registrada para cobrança do saldo."),

  ...cenario("17.7 Compra de manutenção em obra com verba",
    "A obra precisa de material de manutenção, mas está com a verba perto do limite.",
    [
      "Solicitante abre a requisição vinculada à obra.",
      "Na submissão, o sistema calcula o consumo da verba; acima de 80% exibe alerta, em 100% bloqueia.",
      "Dentro da verba, a requisição segue o fluxo normal de cotação e aprovação.",
    ],
    "Gasto da obra controlado em tempo real, evitando estouro de orçamento."),

  L.H2("17.8 Fora de escopo (não fazer pelo sistema)"),
  L.bullet([b("Combustível de pista dos postos: "), run("segue processo próprio, fora do HELIX Compras.")]),
  L.bullet([b("Integração contábil/ERP e portal do fornecedor: "), run("não fazem parte desta versão.")]),
];
