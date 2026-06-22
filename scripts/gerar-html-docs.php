<?php

/**
 * Converte os manuais Markdown de docs/ em HTML print-ready (A4) em output/.
 * Usa league/commonmark (já instalada — sem dependência nova). Sem boot do Laravel.
 *
 * Uso: php scripts/gerar-html-docs.php
 * Depois: abra o HTML no navegador e Ctrl+P → "Salvar como PDF" (A4).
 */

require __DIR__.'/../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

$base = dirname(__DIR__);
$saida = $base.'/output';
if (! is_dir($saida)) {
    mkdir($saida, 0777, true);
}

$docs = [
    'MANUAL-COMPRADORA.md' => 'manual-compradora.html',
    'MANUAL-TECNICO.md' => 'manual-tecnico.html',
    'RUNBOOK-PILOT.md' => 'runbook-pilot.html',
];

$converter = new GithubFlavoredMarkdownConverter([
    'html_input' => 'allow',
    'allow_unsafe_links' => false,
]);

/** Slug no estilo do GitHub (minúsculo, acentos preservados, espaços→hífen). */
function gh_slug(string $texto): string
{
    $t = mb_strtolower(trim($texto), 'UTF-8');
    $t = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $t);
    $t = preg_replace('/\s+/u', '-', $t);

    return trim((string) $t, '-');
}

$css = file_get_contents(__DIR__.'/doc-print.css');

foreach ($docs as $entrada => $arquivoSaida) {
    $md = file_get_contents($base.'/docs/'.$entrada);
    $html = (string) $converter->convert($md);

    // Adiciona id aos cabeçalhos (links internos do índice) e coleta o TOC.
    $usados = [];
    $headers = [];
    $html = preg_replace_callback('/<(h[1-4])>(.*?)<\/\1>/su', function ($m) use (&$usados, &$headers) {
        $tag = $m[1];
        $texto = trim(strip_tags($m[2]));
        $slug = gh_slug($texto) ?: 'sec';
        $orig = $slug;
        $i = 1;
        while (isset($usados[$slug])) {
            $slug = $orig.'-'.(++$i);
        }
        $usados[$slug] = true;

        if ($tag === 'h2' || $tag === 'h3') {
            $headers[] = ['nivel' => (int) substr($tag, 1), 'texto' => $texto, 'slug' => $slug];
        }

        return "<{$tag} id=\"{$slug}\">{$m[2]}</{$tag}>";
    }, $html);

    // Título = primeiro <h1>.
    preg_match('/<h1[^>]*>(.*?)<\/h1>/su', $html, $tm);
    $titulo = isset($tm[1]) ? trim(strip_tags($tm[1])) : $arquivoSaida;

    // TOC automático (h2/h3).
    $toc = '<nav class="toc"><p style="font-weight:700;margin-bottom:8pt;">Índice</p><ol>';
    foreach ($headers as $h) {
        $estilo = $h['nivel'] === 3 ? ' style="margin-left:16pt;list-style:circle;"' : '';
        $toc .= '<li'.$estilo.'><a href="#'.$h['slug'].'">'.htmlspecialchars($h['texto'], ENT_QUOTES, 'UTF-8').'</a></li>';
    }
    $toc .= '</ol></nav>';

    $tituloEsc = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');

    $documento = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$tituloEsc}</title>
    <style>
{$css}
    </style>
</head>
<body>
{$toc}
{$html}
    <footer>
        <p>Comendador Compras v1 | Gerado em 22/06/2026 | &copy; 2026 Rede Comendador</p>
    </footer>
</body>
</html>
HTML;

    file_put_contents($saida.'/'.$arquivoSaida, $documento);
    echo 'Gerado: output/'.$arquivoSaida.' ('.number_format(strlen($documento)).' bytes, '.count($headers)." itens no índice)\n";
}

echo "OK — abra os HTML no navegador e Ctrl+P → Salvar como PDF (A4).\n";
