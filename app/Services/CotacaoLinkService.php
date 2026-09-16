<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\Scopes\UnidadeScope;
use Carbon\CarbonInterface;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Link assinado de resposta de cotação (decisão 11 — parecer OWASP ASVS V2/V3).
 *
 * O link é o ÚNICO canal que grava a proposta do fornecedor. Propriedades:
 *  - token aleatório de 256 bits (random_bytes), entregue só no e-mail; a base guarda
 *    apenas o SHA-256 dele (`token_hash`);
 *  - uma emissão por (cotação, fornecedor); reemitir revoga a anterior;
 *  - `expires_at` = prazo de resposta da cotação — também é o `expires` da assinatura
 *    da URL (dupla trava: assinatura do APP_KEY + hash na base);
 *  - uso ÚNICO na submissão (UPDATE condicional atômico); visualizar não consome;
 *  - revogável pelo comprador.
 */
class CotacaoLinkService
{
    public function __construct(private ActivityRecorder $atividade) {}

    /**
     * Emite um link novo para a cotação (revogando o que houver) e devolve a URL
     * assinada com o token em claro — que não é persistido em lugar nenhum.
     *
     * @return array{link: CotacaoLink, url: string}
     */
    public function emitir(Cotacao $cotacao, CarbonInterface $prazo): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiraEm = $prazo->copy()->endOfDay();

        $link = DB::transaction(function () use ($cotacao, $token, $expiraEm) {
            $this->revogarAtivos($cotacao);

            $link = new CotacaoLink;
            $link->forceFill([
                'cotacao_id' => $cotacao->id,
                'fornecedor_id' => $cotacao->fornecedor_id,
                'token_hash' => self::hash($token),
                'referencia' => (string) Str::ulid(),
                'expires_at' => $expiraEm,
                'criado_por' => auth()->id(),
            ])->save();

            return $link;
        });

        $this->atividade->record('compras.cotacao_link_emitido', $link, null, [
            'actor_id' => auth()->id(),
            'payload' => self::payloadEvento($link),
            'metadata' => ['cotacao_id' => $cotacao->id, 'fornecedor_id' => $cotacao->fornecedor_id, 'expires_at' => $expiraEm->toIso8601String()],
        ]);

        return ['link' => $link, 'url' => self::url($token, $expiraEm)];
    }

    /** Revoga o(s) link(s) ainda utilizáveis da cotação. Devolve quantos revogou. */
    public function revogar(Cotacao $cotacao): int
    {
        $revogados = $this->revogarAtivos($cotacao);

        if ($revogados > 0) {
            $this->atividade->record('compras.cotacao_link_revogado', $cotacao, null, [
                'actor_id' => auth()->id(),
                'metadata' => ['links_revogados' => $revogados],
            ]);
        }

        return $revogados;
    }

    /**
     * Resolve o link pelo token em claro — o ÚNICO lookup sem tenant no contexto: a
     * rota pública não tem sessão nem tenant, e é o próprio token que DESCOBRE o
     * tenant. Todo o resto roda sob TenantContext::runFor((string) $link->tenant_id).
     */
    public function resolver(string $token): ?CotacaoLink
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        return CotacaoLink::withoutTenantScope()->where('token_hash', self::hash($token))->first();
    }

    /**
     * Cotação ainda apta a receber a proposta por este link — chamado SOB o tenant do
     * link. Qualquer inconsistência (link de outro tenant/fornecedor, cotação apagada,
     * já confirmada, requisição fora de cotação) devolve null: a rota responde a mesma
     * página genérica em todos os casos.
     */
    public function cotacaoAberta(CotacaoLink $link): ?Cotacao
    {
        if (! $link->utilizavel()) {
            return null;
        }

        // Escopada pelo tenant do contexto (BelongsToTenant): um link carimbado num
        // tenant nunca alcança a cotação de outro, mesmo com cotacao_id adulterado.
        // O UnidadeScope (recorte por vínculo do USUÁRIO) é fail-closed sem login; aqui
        // não há usuário — a posse é do link, e o tenant já está fixado pelo runFor.
        $cotacao = Cotacao::query()
            ->with([
                'requisicao' => fn ($q) => $q->withoutGlobalScope(UnidadeScope::class),
                'requisicao.itens',
                'fornecedor',
            ])
            ->find($link->cotacao_id);

        if ($cotacao === null
            || (int) $cotacao->fornecedor_id !== (int) $link->fornecedor_id
            || $cotacao->valor !== null
            || $cotacao->cancelada_em !== null
            || $cotacao->requisicao?->status !== StatusRequisicao::EmCotacao) {
            return null;
        }

        return $cotacao;
    }

    /**
     * Consome o link (uso único). UPDATE condicional atômico: duas submissões
     * concorrentes não passam as duas. Deve rodar dentro da transação da gravação.
     */
    public function consumir(CotacaoLink $link): bool
    {
        return CotacaoLink::query()
            ->whereKey($link->id)
            ->whereNull('submetido_em')
            ->whereNull('revogado_em')
            ->where('expires_at', '>', now())
            ->update(['submetido_em' => now()]) === 1;
    }

    /**
     * Payload do evento de domínio SEM o `token_hash` (o default do ActivityRecorder
     * serializa todos os atributos do registro).
     *
     * @return array<string, mixed>
     */
    public static function payloadEvento(CotacaoLink $link): array
    {
        return [
            'id' => $link->id,
            'cotacao_id' => $link->cotacao_id,
            'fornecedor_id' => $link->fornecedor_id,
            'expires_at' => $link->expires_at?->toIso8601String(),
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function url(string $token, CarbonInterface $expiraEm): string
    {
        return URL::temporarySignedRoute('cotacao.proposta', $expiraEm, ['token' => $token]);
    }

    private function revogarAtivos(Cotacao $cotacao): int
    {
        return CotacaoLink::query()
            ->where('cotacao_id', $cotacao->id)
            ->whereNull('submetido_em')
            ->whereNull('revogado_em')
            ->update(['revogado_em' => now(), 'revogado_por' => auth()->id()]);
    }
}
