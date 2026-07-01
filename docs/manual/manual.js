/* Gerador do Manual Oficial do Usuário — HELIX Compras. */
const fs = require("fs");
const path = require("path");
const {
  Document, Packer, Paragraph, TextRun, AlignmentType, HeadingLevel, LevelFormat,
  TableOfContents, Header, Footer, PageNumber, PageBreak, BorderStyle, ImageRun,
} = require("docx");
const L = require("./lib");
const { BRAND } = L;

const VERSION = "1.0";
const DATA = "Junho/2026";

// ── Capa ───────────────────────────────────────────────────────────────────────
function capa() {
  const big = (t, opts) => new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 0 },
    children: [new TextRun({ text: t, ...opts })] });
  return [
    new Paragraph({ spacing: { before: 2600 }, children: [] }),
    big("HELIX", { bold: true, size: 96, color: BRAND.blue }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 200 },
      children: [new TextRun({ text: "C O M P R A S   &   E S T O Q U E", color: BRAND.purple, size: 24, bold: true })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, border: {
        top: { style: BorderStyle.SINGLE, size: 12, color: BRAND.purple, space: 8 } },
      spacing: { after: 500 }, children: [] }),
    big("Manual Oficial do Usuário", { bold: true, size: 52, color: BRAND.ink }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 120, after: 1400 },
      children: [new TextRun({ text: "Sistema de Gestão de Compras (Procurement) — Rede Comendador", size: 24, color: BRAND.slate })] }),
    big(`Versão ${VERSION}`, { size: 24, color: BRAND.slate }),
    big(DATA, { size: 24, color: BRAND.slate }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 1800 },
      children: [new TextRun({ text: "DOCUMENTO CONFIDENCIAL — USO INTERNO", size: 16, color: "94A3B8", bold: true })] }),
  ];
}

// ── Controle do documento ───────────────────────────────────────────────────────
function controle() {
  return [
    L.H1("Controle do Documento"),
    L.table(
      ["Atributo", "Valor"],
      [
        ["Título", "Manual Oficial do Usuário — HELIX Compras"],
        ["Produto", "HELIX Compras & Estoque (módulo de Procurement)"],
        ["Versão do documento", VERSION],
        ["Data de emissão", DATA],
        ["Classificação", "Confidencial — Uso interno"],
        ["Público-alvo", "Solicitantes, Compradora Sênior, Aprovadores, Almoxarifes, Financeiro e Administradores"],
        ["Finalidade", "Treinamento, onboarding, implantação, certificação interna e central de ajuda"],
        ["Idioma", "Português (Brasil)"],
      ],
      [2600, 6760],
    ),
    L.spacer(),
    L.callout("obs", [
      "Este manual reflete o comportamento real do sistema, verificado por navegação direta nas telas e leitura das regras implementadas. As imagens são capturas reais do ambiente.",
      "Onde foram identificadas divergências entre interface e regra de negócio, há uma caixa de ATENÇÃO documentando o ponto e a melhoria proposta.",
    ]),
    L.H2("Histórico de revisões"),
    L.table(
      ["Versão", "Data", "Autor", "Descrição"],
      [["1.0", DATA, "Documentação de Produto", "Emissão inicial do manual oficial."]],
      [1200, 1700, 2800, 3660],
    ),
    L.H2("Convenções visuais"),
    L.P("Ao longo deste manual são utilizadas as seguintes caixas de destaque:"),
    L.callout("info", "Informação contextual relevante para o entendimento da funcionalidade."),
    L.spacer(),
    L.callout("atencao", "Ponto que exige cuidado: pode impactar dados, saldo ou autorização."),
    L.spacer(),
    L.callout("aviso", "Ação de risco ou irreversível, ou divergência que precisa de correção."),
    L.spacer(),
    L.callout("dica", "Atalho ou recomendação que agiliza a operação."),
    L.spacer(),
    L.callout("bp", "Boa prática recomendada pela documentação para qualidade e conformidade."),
  ];
}

function sumario() {
  return [
    L.H1("Sumário"),
    new TableOfContents("Sumário", { hyperlink: true, headingStyleRange: "1-3" }),
  ];
}

// ── Montagem ─────────────────────────────────────────────────────────────────────
const chapters = [
  require("./ch01"), require("./ch02"), require("./ch03"),
  require("./ch04"), require("./ch05"), require("./ch06"),
  require("./ch07"), require("./ch08"), require("./ch09"),
  require("./ch10"), require("./ch11"), require("./ch12"),
  require("./ch13"), require("./ch14"), require("./ch15"),
  require("./ch16"), require("./ch17"), require("./ch18"),
  require("./ch19"), require("./ch20"), require("./ch21"), require("./ch22"),
  require("./ch23"),
].flatMap((c) => c());

const heading = (id, size, color, after, before) => ({
  id, name: id, basedOn: "Normal", next: "Normal", quickFormat: true,
  run: { size, bold: true, font: "Arial", color },
  paragraph: { spacing: { before, after }, outlineLevel: Number(id.slice(-1)) - 1 },
});

const doc = new Document({
  creator: "HELIX",
  title: "Manual Oficial do Usuário — HELIX Compras",
  styles: {
    default: { document: { run: { font: "Arial", size: 21, color: "1F2937" } } },
    paragraphStyles: [
      heading("Heading1", 36, BRAND.blue, 240, 240),
      heading("Heading2", 28, BRAND.ink, 200, 220),
      heading("Heading3", 24, BRAND.slate, 160, 180),
    ],
  },
  numbering: {
    config: [
      { reference: "bullets", levels: [
        { level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 280 } } } },
        { level: 1, format: LevelFormat.BULLET, text: "–", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 1020, hanging: 280 } } } },
      ] },
      { reference: "steps", levels: [
        { level: 0, format: LevelFormat.DECIMAL, text: "%1.", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 300 } } } },
        { level: 1, format: LevelFormat.LOWER_LETTER, text: "%2)", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 1020, hanging: 300 } } } },
      ] },
    ],
  },
  sections: [
    // Capa — sem cabeçalho/rodapé
    { properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 } } },
      children: capa() },
    // Corpo — com cabeçalho/rodapé e numeração
    {
      properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 } } },
      headers: { default: new Header({ children: [new Paragraph({
        alignment: AlignmentType.RIGHT,
        border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: BRAND.blue, space: 4 } },
        children: [new TextRun({ text: "HELIX Compras — Manual Oficial do Usuário", size: 16, color: BRAND.slate })] })] }) },
      footers: { default: new Footer({ children: [new Paragraph({
        border: { top: { style: BorderStyle.SINGLE, size: 4, color: BRAND.line, space: 4 } },
        children: [
          new TextRun({ text: `Versão ${VERSION}  ·  ${DATA}  ·  Confidencial`, size: 15, color: "94A3B8" }),
          new TextRun({ text: "\t\tPágina ", size: 16, color: BRAND.slate }),
          new TextRun({ children: [PageNumber.CURRENT], size: 16, color: BRAND.slate }),
          new TextRun({ text: " de ", size: 16, color: BRAND.slate }),
          new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 16, color: BRAND.slate }),
        ],
        tabStops: [{ type: "right", position: 9360 }],
      })] }) },
      children: [...controle(), ...sumario(), ...chapters],
    },
  ],
});

Packer.toBuffer(doc).then((buf) => {
  const out = path.join(__dirname, "Manual-HELIX-Compras.docx");
  fs.writeFileSync(out, buf);
  console.log("OK ->", out, "(" + (buf.length / 1024 / 1024).toFixed(2) + " MB)");
});
