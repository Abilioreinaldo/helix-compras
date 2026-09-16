<?php

namespace App\Imap;

/**
 * UM header `Authentication-Results` interpretado segundo a RFC 8601.
 *
 * Por que um parser e não regex (3ª auditoria adversarial): o header tem
 * COMENTÁRIOS (CFWS, aninháveis, com `;` dentro — o opendkim escreve
 * `dkim=pass (2048-bit key; unprotected) header.d=...`) e VALORES CITADOS (um
 * local-part pode ser `"x;dkim=pass header.d=alfa.test"@evil.test`). A regex
 * `dkim=pass[^;]*?header\.d=` recusava o primeiro (falso negativo) e aceitava o
 * segundo (bypass). Aqui o texto é tokenizado — comentário é descartado inteiro,
 * aspas viram UM valor — e só então lido como `authserv-id; método=resultado
 * prop=valor ...`.
 *
 * FAIL-CLOSED: qualquer coisa fora da gramática (aspas/comentário sem fechar,
 * `método` sem `=`, dois carimbos colados, header sem authserv-id) devolve null
 * — o consumidor trata como "não autenticado", nunca tenta adivinhar.
 *
 * Tolerâncias deliberadas (vistas em MTAs reais, sem abrir bypass): propriedade
 * sem `ptype.` (`action=none` do M365) e `=` colado ao fim de um valor (padding
 * base64 em `header.b`).
 */
final class AuthenticationResults
{
    /**
     * @param  list<array{metodo: string, resultado: string, props: array<string, string>}>  $resultados
     */
    private function __construct(
        public readonly string $authservId,
        public readonly array $resultados,
    ) {}

    public static function parse(string $cabecalho): ?self
    {
        $tokens = self::tokenizar($cabecalho);

        if ($tokens === null || $tokens === []) {
            return null;
        }

        $i = 0;
        $n = count($tokens);

        // authserv-id = value (token ou quoted-string).
        if (! in_array($tokens[$i]['tipo'], ['palavra', 'aspas'], true)) {
            return null;
        }
        $authservId = mb_strtolower($tokens[$i]['valor']);
        $i++;

        // authres-version opcional (dígitos) antes do primeiro ";".
        if ($i < $n && $tokens[$i]['tipo'] === 'palavra' && ctype_digit($tokens[$i]['valor'])) {
            $i++;
        }

        $resultados = [];

        while ($i < $n) {
            if ($tokens[$i]['tipo'] !== ';') {
                return null;
            }
            $i++;

            if ($i >= $n) {
                break; // ";" final tolerado
            }

            // no-result: "; none"
            if ($tokens[$i]['tipo'] === 'palavra' && mb_strtolower($tokens[$i]['valor']) === 'none'
                && ($i + 1 >= $n || $tokens[$i + 1]['tipo'] === ';')) {
                $i++;

                continue;
            }

            // methodspec = method "=" result
            if ($tokens[$i]['tipo'] !== 'palavra' || ($tokens[$i + 1]['tipo'] ?? null) !== '='
                || ($tokens[$i + 2]['tipo'] ?? null) !== 'palavra') {
                return null;
            }

            $metodo = mb_strtolower(explode('/', $tokens[$i]['valor'])[0]);
            $resultado = mb_strtolower($tokens[$i + 2]['valor']);
            $i += 3;

            // reasonspec / propspec: palavra "=" valor, até o próximo ";".
            $props = [];
            while ($i < $n && $tokens[$i]['tipo'] !== ';') {
                if ($tokens[$i]['tipo'] !== 'palavra' || ($tokens[$i + 1]['tipo'] ?? null) !== '=') {
                    return null;
                }

                $chave = mb_strtolower($tokens[$i]['valor']);
                $i += 2;

                $valor = self::lerValor($tokens, $i);
                if ($valor === null) {
                    return null;
                }

                // Propriedade repetida é ambígua (qual vale?) — recusa.
                if (array_key_exists($chave, $props)) {
                    return null;
                }

                $props[$chave] = $valor;
            }

            $resultados[] = ['metodo' => $metodo, 'resultado' => $resultado, 'props' => $props];
        }

        return new self($authservId, $resultados);
    }

    /**
     * Só o authserv-id (primeiro token), para escolher QUAL header ler sem exigir
     * que os demais sejam válidos.
     */
    public static function authservIdDe(string $cabecalho): ?string
    {
        $tokens = self::tokenizar($cabecalho);

        if ($tokens === null || $tokens === [] || ! in_array($tokens[0]['tipo'], ['palavra', 'aspas'], true)) {
            return null;
        }

        return mb_strtolower($tokens[0]['valor']);
    }

    /** @return list<array{metodo: string, resultado: string, props: array<string, string>}> */
    public function doMetodo(string $metodo): array
    {
        return array_values(array_filter($this->resultados, fn (array $r) => $r['metodo'] === $metodo));
    }

    /**
     * pvalue = value / [ [ local-part ] "@" ] domain-name — um local-part citado
     * vem COLADO ao `@domínio` (`"x;y"@evil.test`); `=` colado é padding base64.
     *
     * @param  list<array{tipo: string, valor: string, colado: bool}>  $tokens
     */
    private static function lerValor(array $tokens, int &$i): ?string
    {
        if (! isset($tokens[$i]) || ! in_array($tokens[$i]['tipo'], ['palavra', 'aspas'], true)) {
            return null;
        }

        $valor = $tokens[$i]['valor'];
        $i++;

        while (isset($tokens[$i]) && $tokens[$i]['colado']
            && ($tokens[$i]['tipo'] === '=' || ($tokens[$i]['tipo'] === 'palavra' && str_starts_with($tokens[$i]['valor'], '@')))) {
            $valor .= $tokens[$i]['valor'];
            $i++;
        }

        return $valor;
    }

    /**
     * Tokens: `palavra`, `aspas` (quoted-string já sem aspas/escapes), `;` e `=`.
     * Espaço e comentário (aninhável, com escapes) separam tokens e são
     * descartados; `colado` diz se o token veio SEM separador antes.
     *
     * @return list<array{tipo: string, valor: string, colado: bool}>|null
     */
    private static function tokenizar(string $texto): ?array
    {
        // Desdobra linhas (folding) e normaliza espaços de controle.
        $texto = preg_replace('/\r?\n[ \t]+/', ' ', $texto) ?? '';

        $tokens = [];
        $len = strlen($texto);
        $colado = false;

        for ($p = 0; $p < $len;) {
            $ch = $texto[$p];

            if ($ch === ' ' || $ch === "\t" || $ch === "\r" || $ch === "\n") {
                $colado = false;
                $p++;

                continue;
            }

            if ($ch === '(') {
                $profundidade = 0;
                for (; $p < $len; $p++) {
                    if ($texto[$p] === '\\') {
                        $p++;

                        continue;
                    }
                    if ($texto[$p] === '(') {
                        $profundidade++;
                    } elseif ($texto[$p] === ')') {
                        $profundidade--;
                        if ($profundidade === 0) {
                            break;
                        }
                    }
                }

                if ($profundidade !== 0) {
                    return null; // comentário sem fechar
                }

                $p++;
                $colado = false;

                continue;
            }

            if ($ch === ')') {
                return null;
            }

            if ($ch === ';' || $ch === '=') {
                $tokens[] = ['tipo' => $ch, 'valor' => $ch, 'colado' => $colado];
                $colado = true;
                $p++;

                continue;
            }

            if ($ch === '"') {
                $valor = '';
                $fechou = false;
                for ($p++; $p < $len; $p++) {
                    if ($texto[$p] === '\\' && $p + 1 < $len) {
                        $valor .= $texto[++$p];

                        continue;
                    }
                    if ($texto[$p] === '"') {
                        $fechou = true;
                        break;
                    }
                    $valor .= $texto[$p];
                }

                if (! $fechou) {
                    return null; // aspas sem fechar
                }

                $tokens[] = ['tipo' => 'aspas', 'valor' => $valor, 'colado' => $colado];
                $colado = true;
                $p++;

                continue;
            }

            $inicio = $p;
            while ($p < $len && strpbrk($texto[$p], " \t\r\n;=()\"") === false) {
                $p++;
            }

            $tokens[] = ['tipo' => 'palavra', 'valor' => substr($texto, $inicio, $p - $inicio), 'colado' => $colado];
            $colado = true;
        }

        return $tokens;
    }
}
