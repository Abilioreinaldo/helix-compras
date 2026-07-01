/* Biblioteca de helpers para o Manual HELIX Compras (docx-js). */
const fs = require("fs");
const path = require("path");
const {
  Paragraph, TextRun, Table, TableRow, TableCell, ImageRun, AlignmentType,
  HeadingLevel, BorderStyle, WidthType, ShadingType, VerticalAlign, PageBreak,
} = require("docx");

const ASSETS = path.join(__dirname, "assets");
const CONTENT_W = 9360; // US Letter, margens 1"
const BRAND = { blue: "2563EB", purple: "7C3AED", ink: "1E293B", slate: "475569", line: "CBD5E1" };

// ── Dimensões de PNG (IHDR) ──────────────────────────────────────────────────
function pngSize(file) {
  const b = fs.readFileSync(path.join(ASSETS, file));
  return { w: b.readUInt32BE(16), h: b.readUInt32BE(20) };
}

// ── Texto ────────────────────────────────────────────────────────────────────
function run(text, opts = {}) { return new TextRun({ text, ...opts }); }

function H1(text) {
  return new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true,
    children: [new TextRun({ text, color: BRAND.blue })] });
}
function H2(text) {
  return new Paragraph({ heading: HeadingLevel.HEADING_2,
    children: [new TextRun({ text, color: BRAND.ink })] });
}
function H3(text) {
  return new Paragraph({ heading: HeadingLevel.HEADING_3,
    children: [new TextRun({ text, color: BRAND.slate })] });
}
function P(children, opts = {}) {
  const kids = typeof children === "string" ? [new TextRun(children)] : children;
  return new Paragraph({ spacing: { after: 140, line: 276 }, children: kids, ...opts });
}
function bullet(children, level = 0) {
  const kids = typeof children === "string" ? [new TextRun(children)] : children;
  return new Paragraph({ numbering: { reference: "bullets", level }, spacing: { after: 60 }, children: kids });
}
function numbered(children, level = 0) {
  const kids = typeof children === "string" ? [new TextRun(children)] : children;
  return new Paragraph({ numbering: { reference: "steps", level }, spacing: { after: 80 }, children: kids });
}
function spacer() { return new Paragraph({ children: [new TextRun("")], spacing: { after: 60 } }); }
function pageBreak() { return new Paragraph({ children: [new PageBreak()] }); }

// ── Caixas de destaque ────────────────────────────────────────────────────────
const BOX = {
  info:    { fill: "E8F0FE", border: "2563EB", label: "ℹ INFORMAÇÃO" },
  aviso:   { fill: "FDECEA", border: "C0392B", label: "⚠ AVISO" },
  atencao: { fill: "FEF3E2", border: "D68910", label: "❗ ATENÇÃO" },
  dica:    { fill: "E9F7EF", border: "1E8449", label: "💡 DICA" },
  bp:      { fill: "EFEAF8", border: "7C3AED", label: "★ BOA PRÁTICA" },
  obs:     { fill: "F1F3F5", border: "868E96", label: "✎ OBSERVAÇÃO" },
};
function callout(type, lines) {
  const c = BOX[type] || BOX.info;
  const arr = Array.isArray(lines) ? lines : [lines];
  const kids = [
    new Paragraph({ spacing: { after: 40 }, children: [new TextRun({ text: c.label, bold: true, color: c.border, size: 18 })] }),
    ...arr.map((t, i) => new Paragraph({ spacing: { after: i === arr.length - 1 ? 0 : 40 },
      children: typeof t === "string" ? [new TextRun({ text: t, size: 20 })] : t })),
  ];
  return new Table({
    width: { size: CONTENT_W, type: WidthType.DXA }, columnWidths: [CONTENT_W],
    rows: [new TableRow({ children: [new TableCell({
      width: { size: CONTENT_W, type: WidthType.DXA },
      shading: { fill: c.fill, type: ShadingType.CLEAR },
      margins: { top: 120, bottom: 120, left: 160, right: 160 },
      borders: {
        top: { style: BorderStyle.SINGLE, size: 2, color: c.fill },
        left: { style: BorderStyle.SINGLE, size: 24, color: c.border },
        bottom: { style: BorderStyle.SINGLE, size: 2, color: c.fill },
        right: { style: BorderStyle.SINGLE, size: 2, color: c.fill },
      },
      children: kids,
    })] })],
  });
}

// ── Tabelas ───────────────────────────────────────────────────────────────────
function cell(text, w, opts = {}) {
  const kids = Array.isArray(text) ? text
    : [new Paragraph({ spacing: { after: 0 }, alignment: opts.align,
        children: [new TextRun({ text: String(text), bold: opts.bold, color: opts.color, size: opts.size || 19 })] })];
  return new TableCell({
    width: { size: w, type: WidthType.DXA },
    shading: opts.fill ? { fill: opts.fill, type: ShadingType.CLEAR } : undefined,
    verticalAlign: VerticalAlign.CENTER,
    margins: { top: 70, bottom: 70, left: 110, right: 110 },
    children: kids,
  });
}
const TB = { style: BorderStyle.SINGLE, size: 1, color: BRAND.line };
function table(headers, rows, widths) {
  const borders = { top: TB, left: TB, bottom: TB, right: TB, insideHorizontal: TB, insideVertical: TB };
  const headRow = new TableRow({ tableHeader: true, children:
    headers.map((h, i) => cell(h, widths[i], { bold: true, color: "FFFFFF", fill: BRAND.blue })) });
  const bodyRows = rows.map((r, ri) => new TableRow({ children:
    r.map((c, i) => cell(c, widths[i], { fill: ri % 2 ? "F8FAFC" : undefined })) }));
  return new Table({ width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA },
    columnWidths: widths, borders, rows: [headRow, ...bodyRows] });
}

// Tabela de definição de campo (rótulo | valor), vertical.
function fieldTable(num, name, rows) {
  const W1 = 2200, W2 = CONTENT_W - W1;
  const borders = { top: TB, left: TB, bottom: TB, right: TB, insideHorizontal: TB, insideVertical: TB };
  const head = new TableRow({ children: [
    new TableCell({ width: { size: CONTENT_W, type: WidthType.DXA }, columnSpan: 2,
      shading: { fill: BRAND.purple, type: ShadingType.CLEAR }, margins: { top: 70, bottom: 70, left: 110, right: 110 },
      children: [new Paragraph({ children: [new TextRun({ text: `${num}  ${name}`, bold: true, color: "FFFFFF", size: 20 })] })] }),
  ] });
  const body = rows.map(([k, v]) => new TableRow({ children: [
    cell(k, W1, { bold: true, fill: "F1F5F9" }), cell(v, W2),
  ] }));
  return new Table({ width: { size: CONTENT_W, type: WidthType.DXA }, columnWidths: [W1, W2], borders, rows: [head, ...body] });
}

// ── Screenshot + legenda ───────────────────────────────────────────────────────
let FIG = 0;
function figura(file, legenda, maxW = 600) {
  const { w, h } = pngSize(file);
  const width = Math.min(maxW, w);
  const height = Math.round(width * (h / w));
  const tw = Math.min(CONTENT_W, width * 15 + 240); // moldura ~ largura da imagem (1px≈15dxa)
  const FB = { style: BorderStyle.SINGLE, size: 4, color: BRAND.line };
  FIG += 1;
  return [
    new Table({
      width: { size: tw, type: WidthType.DXA }, columnWidths: [tw], alignment: AlignmentType.CENTER,
      rows: [new TableRow({ children: [new TableCell({
        width: { size: tw, type: WidthType.DXA },
        margins: { top: 80, bottom: 80, left: 80, right: 80 },
        borders: { top: FB, left: FB, bottom: FB, right: FB },
        children: [new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 0 },
          children: [new ImageRun({ type: "png", data: fs.readFileSync(path.join(ASSETS, file)),
            transformation: { width, height },
            altText: { title: legenda, description: legenda, name: file } })] })],
      })] })],
    }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 60, after: 180 },
      children: [new TextRun({ text: `Figura ${FIG} — ${legenda}`, italics: true, color: BRAND.slate, size: 18 })] }),
  ];
}

// ── Fluxograma vertical simples (passos com setas) ─────────────────────────────
function fluxo(steps) {
  const out = [];
  steps.forEach((s, i) => {
    out.push(new Table({
      width: { size: 5400, type: WidthType.DXA }, columnWidths: [5400],
      alignment: AlignmentType.CENTER,
      rows: [new TableRow({ children: [new TableCell({
        width: { size: 5400, type: WidthType.DXA },
        shading: { fill: "EEF2FF", type: ShadingType.CLEAR },
        margins: { top: 90, bottom: 90, left: 120, right: 120 },
        borders: { top: { style: BorderStyle.SINGLE, size: 6, color: BRAND.blue },
          left: { style: BorderStyle.SINGLE, size: 6, color: BRAND.blue },
          bottom: { style: BorderStyle.SINGLE, size: 6, color: BRAND.blue },
          right: { style: BorderStyle.SINGLE, size: 6, color: BRAND.blue } },
        children: [new Paragraph({ alignment: AlignmentType.CENTER,
          children: [new TextRun({ text: s, bold: true, color: BRAND.ink, size: 20 })] })] })] })],
    }));
    if (i < steps.length - 1) {
      out.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 20, after: 20 },
        children: [new TextRun({ text: "▼", color: BRAND.purple, size: 22 })] }));
    }
  });
  return out;
}

module.exports = {
  fs, path, ASSETS, CONTENT_W, BRAND, pngSize,
  run, H1, H2, H3, P, bullet, numbered, spacer, pageBreak,
  callout, cell, table, fieldTable, figura, fluxo,
  AlignmentType, Paragraph, TextRun, ImageRun, HeadingLevel, BorderStyle, WidthType, ShadingType,
};
