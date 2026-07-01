const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("Anexo A — Inconsistências e Melhorias"),
  L.P("Durante a elaboração deste manual, com navegação direta no sistema, foram identificados os pontos abaixo. Conforme a política de documentação, cada um é registrado com a melhoria proposta e o status."),

  L.H2("A.1 Registro de achados"),
  L.table(
    ["Tela", "Achado", "Melhoria proposta", "Status"],
    [
      [
        "Painel de Aprovação",
        "O subtítulo exibia o código de entidade “&mdash;” em vez do travessão (—), por escape de HTML no atributo.",
        "Usar o caractere de travessão diretamente no subtítulo.",
        "Corrigido nesta versão.",
      ],
    ],
    [1900, 3360, 2900, 1200],
  ),
  L.callout("obs", "Achados de menor impacto visual não interrompem a operação, mas foram tratados para manter o padrão premium do produto."),

  L.H2("A.2 Como reportar novos achados"),
  L.P([
    run("Ao encontrar comportamento divergente ou texto incorreto, registre: a "),
    b("tela"), run(", o "), b("passo para reproduzir"), run(", o "),
    b("resultado observado"), run(" e o "), b("esperado"),
    run(". Encaminhe ao Administrador do sistema ou à equipe de produto, que avaliam e priorizam a correção em uma próxima versão."),
  ]),
];
