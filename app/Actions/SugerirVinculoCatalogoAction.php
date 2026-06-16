<?php

namespace App\Actions;

use App\Models\CatalogoItem;
use App\Models\SaldoEstoque;
use Illuminate\Support\Collection;

class SugerirVinculoCatalogoAction
{
    /**
     * Limite mínimo de score para um candidato aparecer na lista de sugestões.
     */
    private const SCORE_MINIMO = 0.30;

    /**
     * Sugere candidatos do catálogo para vincular a um saldo de estoque sem vínculo.
     *
     * Pré-filtra via LIKE em palavras da descrição normalizada (reduz candidatos antes
     * do scoring em PHP) e calcula um score combinando similar_text() percentual e
     * Jaccard de tokens (interseção/união de palavras).
     *
     * @return Collection<int, array{item: CatalogoItem, score: float, confianca: string}>
     */
    public function execute(SaldoEstoque $saldo): Collection
    {
        // QA BUG-04: saldo já vinculado ao catálogo não recebe sugestões.
        if ($saldo->item_catalogo_id !== null) {
            return collect();
        }

        $descricaoSaldo = $saldo->descricao_normalizada;
        $tokensSaldo = $this->tokenizar($descricaoSaldo);

        if (empty($tokensSaldo)) {
            return collect();
        }

        $query = CatalogoItem::where('ativo', true);

        $query->where(function ($q) use ($tokensSaldo) {
            foreach ($tokensSaldo as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }
                $q->orWhere('descricao', 'like', '%'.$this->escaparLike($token).'%');
            }
        });

        $candidatos = $query->get();

        return $candidatos
            ->map(function (CatalogoItem $item) use ($descricaoSaldo, $tokensSaldo) {
                $score = $this->calcularScore($descricaoSaldo, $tokensSaldo, $item->descricao);

                return [
                    'item' => $item,
                    'score' => $score,
                    'confianca' => $this->nivelConfianca($score),
                ];
            })
            ->filter(fn (array $candidato) => $candidato['score'] >= self::SCORE_MINIMO)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Calcula o score combinado: 60% similar_text() percentual + 40% Jaccard de tokens.
     */
    private function calcularScore(string $descricaoSaldo, array $tokensSaldo, string $descricaoCatalogo): float
    {
        $descricaoCatalogoNormalizada = SaldoEstoque::normalizarDescricao($descricaoCatalogo);

        similar_text($descricaoSaldo, $descricaoCatalogoNormalizada, $percentualSimilaridade);
        $percentualSimilaridade /= 100;

        $tokensCatalogo = $this->tokenizar($descricaoCatalogoNormalizada);
        $jaccard = $this->jaccard($tokensSaldo, $tokensCatalogo);

        return round(($percentualSimilaridade * 0.6) + ($jaccard * 0.4), 4);
    }

    /**
     * Interseção/união de dois conjuntos de tokens (similaridade de Jaccard).
     */
    private function jaccard(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) && empty($tokensB)) {
            return 1.0;
        }

        $setA = array_unique($tokensA);
        $setB = array_unique($tokensB);

        $intersecao = count(array_intersect($setA, $setB));
        $uniao = count(array_unique(array_merge($setA, $setB)));

        return $uniao > 0 ? $intersecao / $uniao : 0.0;
    }

    /**
     * Quebra a descrição normalizada em tokens (palavras) para comparação.
     *
     * @return array<int, string>
     */
    private function tokenizar(string $descricao): array
    {
        return array_values(array_filter(explode(' ', trim($descricao)), fn ($token) => $token !== ''));
    }

    private function escaparLike(string $token): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token);
    }

    private function nivelConfianca(float $score): string
    {
        return match (true) {
            $score >= 0.85 => 'alta',
            $score >= 0.60 => 'media',
            default => 'baixa',
        };
    }
}
