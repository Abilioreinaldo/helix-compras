<?php

namespace App\Services;

/**
 * Extrai valor e prazo do corpo de um e-mail de resposta de cotação.
 *
 * É uma SUGESTÃO (advisory): a compradora sempre confirma. Por isso o parser é
 * tolerante e retorna null quando não tem confiança — nunca "chuta".
 */
class ParseadorRespostaEmailService
{
    /**
     * Extrai o valor (R$) do corpo. Prioriza rótulos (Valor/Preço/Total) antes do R$ solto.
     */
    public static function extrairValor(string $corpo): ?float
    {
        $padroes = [
            '/(?:valor|pre[çc]o|total)\s*:?\s*R?\$?\s*([\d.,]+)/iu',
            '/R\$\s*([\d.,]+)/iu',
        ];

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $corpo, $m)) {
                $numero = self::normalizarNumero($m[1]);
                if ($numero !== null) {
                    return $numero;
                }
            }
        }

        return null;
    }

    /**
     * Extrai o prazo em dias do corpo. Aceita "Prazo: 15 dias", "Entrega: 15 dias",
     * "15 dias úteis", "15 d.u." e, por último, "15 dias".
     */
    public static function extrairPrazo(string $corpo): ?int
    {
        $padroes = [
            '/(?:prazo|entrega)\s*:?\s*(\d+)\s*dias?/iu',
            '/(\d+)\s*dias?\s*[úu]teis/iu',
            '/(\d+)\s*d\.?\s*u\.?\b/iu',
            '/(\d+)\s*dias?/iu',
        ];

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $corpo, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Normaliza um número escrito em formato BR (ou simples) para float.
     * "1.500,50" -> 1500.50 | "150,00" -> 150.0 | "1.500" -> 1500.0 | "150.50" -> 150.50 | "150" -> 150.0
     */
    private static function normalizarNumero(string $bruto): ?float
    {
        $bruto = trim($bruto);
        if ($bruto === '' || $bruto === '.' || $bruto === ',') {
            return null;
        }

        if (str_contains($bruto, ',')) {
            // Formato BR: ponto = separador de milhar, vírgula = decimal.
            $bruto = str_replace('.', '', $bruto);
            $bruto = str_replace(',', '.', $bruto);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $bruto)) {
            // Sem vírgula, mas com grupos de milhar ("1.500", "1.500.000").
            $bruto = str_replace('.', '', $bruto);
        }
        // Demais casos ("150", "150.50") já estão com ponto decimal (ou inteiro).

        if (! is_numeric($bruto)) {
            return null;
        }

        return (float) $bruto;
    }
}
