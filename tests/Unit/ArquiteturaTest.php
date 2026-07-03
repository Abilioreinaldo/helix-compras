<?php

/*
|--------------------------------------------------------------------------
| Testes de arquitetura — guardas contra regressões estruturais.
|--------------------------------------------------------------------------
|
| São varreduras de arquivo (sem banco), rápidas e determinísticas. Cada teste
| trava um "ponto cego" recorrente deste projeto para que uma regressão futura
| falhe no CI em vez de vazar para produção (MySQL). Se um baseline precisar
| mudar, é uma decisão consciente — ajuste com revisão.
|
*/

/**
 * Caminhos absolutos de todos os .php sob app/.
 *
 * @return array<int, string>
 */
function arquivosDaApp(): array
{
    $base = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    $arquivos = [];
    foreach ($iterator as $arquivo) {
        if ($arquivo->isFile() && $arquivo->getExtension() === 'php') {
            $arquivos[] = $arquivo->getPathname();
        }
    }
    sort($arquivos);

    return $arquivos;
}

/**
 * Conteúdo bruto dos literais de string de um arquivo PHP, ignorando
 * comentários e código. Usado para inspecionar SQL embutido sem falso-positivo
 * de comentários (ex.: "// sem strftime").
 *
 * @return array<int, string>
 */
function literaisDeString(string $codigo): array
{
    $strings = [];
    foreach (token_get_all($codigo) as $token) {
        if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $strings[] = $token[1];
        }
    }

    return $strings;
}

/**
 * Detecta chamada a alguma função GLOBAL (por nome), ignorando métodos
 * (`->x()` / `::x()`), definições de função e ocorrências em comentários/strings.
 *
 * @param  array<int, string>  $nomes
 */
function usaFuncaoGlobal(string $codigo, array $nomes): bool
{
    $tokens = token_get_all($codigo);
    $total = count($tokens);

    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];
        if (! is_array($token) || $token[0] !== T_STRING || ! in_array($token[1], $nomes, true)) {
            continue;
        }

        $anterior = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $anterior = $tokens[$j];
            break;
        }

        $tiposMetodo = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR];
        if (is_array($anterior) && in_array($anterior[0], $tiposMetodo, true)) {
            continue;
        }

        $proximo = null;
        for ($j = $i + 1; $j < $total; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                continue;
            }
            $proximo = $tokens[$j];
            break;
        }

        if ($proximo === '(') {
            return true;
        }
    }

    return false;
}

test('SQL com função de data é driver-aware (ramo por getDriverName)', function () {
    // Regra de ouro: testes rodam SQLite, produção é MySQL. julianday/strftime só
    // existem no SQLite; TIMESTAMPDIFF/DATE_FORMAT só no MySQL. Qualquer arquivo que
    // embuta uma dessas em SQL precisa ramificar por DB::getDriverName().
    $funcoesDialeto = ['julianday', 'strftime', 'TIMESTAMPDIFF', 'DATE_FORMAT'];
    $violacoes = [];

    foreach (arquivosDaApp() as $arquivo) {
        $codigo = file_get_contents($arquivo);

        $embuteFuncaoDialeto = false;
        foreach (literaisDeString($codigo) as $literal) {
            foreach ($funcoesDialeto as $funcao) {
                if (stripos($literal, $funcao.'(') !== false) {
                    $embuteFuncaoDialeto = true;
                    break 2;
                }
            }
        }

        if ($embuteFuncaoDialeto && ! str_contains($codigo, 'getDriverName')) {
            $violacoes[] = basename($arquivo);
        }
    }

    expect($violacoes)->toBe([]);
});

test('sem funções de debug (dd/dump/ray/var_dump) no código de produção', function () {
    $debug = ['dd', 'dump', 'ray', 'var_dump'];
    $violacoes = [];

    foreach (arquivosDaApp() as $arquivo) {
        if (usaFuncaoGlobal(file_get_contents($arquivo), $debug)) {
            $violacoes[] = basename($arquivo);
        }
    }

    expect($violacoes)->toBe([]);
});

test('uploads não usam disco público (arquivos de negócio ficam em disco privado)', function () {
    // Documentos anexados (cotações etc.) contêm dados de negócio e não podem
    // ficar em disco público servido diretamente. Downloads passam por controller
    // com abort_unless. Ver DownloadArquivoCotacaoController / RegistrarCotacaoAction.
    $violacoes = [];

    foreach (arquivosDaApp() as $arquivo) {
        $codigo = file_get_contents($arquivo);
        $usaPublico = preg_match('/disk\(\s*[\'"]public[\'"]/', $codigo) === 1
            || preg_match('/store[A-Za-z]*\([^)]*[\'"]public[\'"]/', $codigo) === 1
            || str_contains($codigo, 'storePublicly');

        if ($usaPublico) {
            $violacoes[] = basename($arquivo);
        }
    }

    expect($violacoes)->toBe([]);
});

test('nº de withoutGlobalScopes() não cresce sem revisão (baseline 116)', function () {
    // Cada withoutGlobalScopes() fura o isolamento por unidade — é uma decisão de
    // segurança. Se este número subir, revise o novo call-site (precisa de guarda
    // admin/role? escopo de unidade explícito?) e só então atualize o baseline.
    // 116: +1 em RequisicaoPolicy::view (checagem de vínculo de unidade, padrão de
    // FormularioRequisicao) ao centralizar a autorização de Requisições.
    $total = 0;
    foreach (arquivosDaApp() as $arquivo) {
        $total += substr_count(file_get_contents($arquivo), 'withoutGlobalScopes(');
    }

    expect($total)->toBeLessThanOrEqual(116);
});

test('e-mail transacional não promete fila sem ShouldQueue', function () {
    // Achado da revisão: os Mailables usam o trait Queueable mas NÃO implementam
    // ShouldQueue, então Mail::to()->send() envia de forma SÍNCRONA (o trait sozinho
    // não enfileira). Enquanto nenhum Mailable declarar ShouldQueue, o código não pode
    // despachar por fila (Mail::queue() / ->queue(new ...)) — isso enfileiraria sem o
    // contrato e tornaria falsa qualquer promessa de "fila" no runbook. Se um dia o
    // envio virar assíncrono, adicione ShouldQueue ao Mailable (aí este guard libera).
    $mailDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Mail';
    $mailables = is_dir($mailDir) ? glob($mailDir.DIRECTORY_SEPARATOR.'*.php') : [];

    $comShouldQueue = array_filter($mailables, function (string $arquivo) {
        return preg_match('/implements[^{]*\bShouldQueue\b/', file_get_contents($arquivo)) === 1;
    });

    if ($comShouldQueue !== []) {
        // Envio assíncrono declarado — exige worker (documentar no runbook). Nada a barrar.
        expect($comShouldQueue)->not->toBeEmpty();

        return;
    }

    $despachaPorFila = [];
    foreach (arquivosDaApp() as $arquivo) {
        $codigo = file_get_contents($arquivo);
        if (preg_match('/Mail::queue\s*\(/', $codigo) === 1 || preg_match('/->queue\s*\(\s*new\s/', $codigo) === 1) {
            $despachaPorFila[] = basename($arquivo);
        }
    }

    expect($despachaPorFila)->toBe([]);
});
