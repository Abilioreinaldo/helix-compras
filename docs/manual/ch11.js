const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("11. Estoque, Lote/FEFO e Inventário"),
  L.P("O módulo de estoque controla o saldo de cada item por unidade, as movimentações, o controle de lote e validade (para itens que exigem) e o inventário. Cada unidade enxerga o próprio estoque; Compradora e Admin veem a rede."),

  L.H2("11.1 Saldo de estoque"),
  L.P("O saldo é a quantidade disponível de um item em uma unidade. Regras fundamentais:"),
  L.bullet("O saldo nunca fica negativo — uma saída sem saldo suficiente é bloqueada."),
  L.bullet("Item abaixo do estoque mínimo (cap. 12) gera alerta e sugestão de nova requisição."),
  L.bullet("Toda movimentação registra item, quantidade, tipo, origem (pedido/requisição/ajuste), usuário e data."),
  ...L.figura("31-almox-estoque.png", "Saldos de estoque por unidade."),

  L.H2("11.2 Tipos de movimentação"),
  L.table(
    ["Tipo", "Quando ocorre"],
    [
      ["Entrada", "Automática ao confirmar o recebimento de um pedido na unidade de destino."],
      ["Saída", "Por requisição interna de material: o colaborador solicita, o almoxarife atende e baixa do saldo."],
      ["Transferência", "Saída na unidade de origem + entrada na de destino, em um movimento único rastreável."],
      ["Ajuste", "Correção via inventário, restrita a Admin/Almoxarife e sempre com justificativa."],
    ],
    [1900, 7460],
  ),

  L.H2("11.3 Mapa de Estoque"),
  L.P("O Mapa de Estoque dá a posição visual do estoque por item, lote, validade e unidade — útil para enxergar concentração, vencimentos próximos e rupturas."),
  ...L.figura("32-almox-mapa-estoque.png", "Mapa de Estoque — posição por item, lote e validade."),

  L.H2("11.4 Controle de lote e validade (FEFO)"),
  L.P("Itens que exigem rastreio (por exemplo, insumos de produção da cervejaria) têm o controle de lote ligado. Nesse caso, o saldo é mantido por lote e validade, e as saídas seguem a regra FEFO."),
  L.callout("info", "FEFO (First Expired, First Out): o lote que vence primeiro sai primeiro. O sistema seleciona automaticamente os lotes na saída, priorizando os de validade mais próxima, para reduzir perdas por vencimento."),
  L.callout("atencao", "Quando uma saída debita um lote já vencido, o sistema apenas alerta visualmente — não bloqueia. Cabe ao almoxarife avaliar antes de confirmar."),

  L.H2("11.5 Saída por requisição de material"),
  L.P("Diferente da requisição de compra, a requisição de material baixa diretamente do estoque existente. O colaborador solicita, e o almoxarife da unidade atende, dando saída do saldo (respeitando FEFO quando houver lote)."),

  L.H2("11.6 Inventário"),
  L.P("O inventário é a contagem periódica que confronta o saldo do sistema com o contado fisicamente, permitindo ajustes em lote."),
  ...L.figura("33-almox-inventario.png", "Sessão de inventário — conferência e ajuste."),
  L.H3("Como conduzir um inventário"),
  L.numbered("Abra uma sessão de inventário para a unidade."),
  L.numbered("Registre a quantidade contada de cada item (a tela mostra o saldo do sistema ao lado)."),
  L.numbered("Revise as divergências (sistema × contado)."),
  L.numbered("Aplique o inventário: os ajustes são gerados em lote, com a justificativa da contagem."),
  L.callout("aviso", "O ajuste de inventário altera o saldo oficial e valoriza o estoque. É uma ação de impacto contábil — exige justificativa e deve ser conferida antes de aplicar. Itens com controle de lote consideram a validade na contagem."),
  L.callout("bp", "Faça inventários cíclicos (por categoria ou criticidade) em vez de um único inventário geral: a contagem fica mais precisa e o impacto operacional é menor."),

  L.H2("11.7 Erros comuns"),
  L.table(
    ["Situação", "Causa / Solução"],
    [
      ["Saída bloqueada por saldo", "Não há saldo suficiente. Verifique o saldo real; se necessário, abra requisição de compra para repor."],
      ["Item não controla lote, mas precisa", "Ligue o controle de lote no catálogo (Admin). Atenção: ligar o controle exige tratamento do saldo existente."],
      ["Divergência recorrente no inventário", "Reveja o processo de saída e entrada da unidade; movimentações fora do sistema são a causa mais comum."],
    ],
    [3000, 6360],
  ),
];
