<?php

namespace App\Actions;

use App\Enums\StatusRequisicao;
use App\Imap\MensagemEmail;
use App\Imap\VerificadorAutenticidadeEmail;
use App\Mail\RespostaCotacaoPorEmailRecebida;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\Scopes\UnidadeScope;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Processa UMA resposta por e-mail de fornecedor — e NÃO grava proposta (decisão 11).
 *
 * Desde a decisão 11 o link assinado é o único canal que grava proposta. O e-mail
 * vira só AVISO ao comprador ("resposta recebida por e-mail, conferir"), sem alterar
 * nenhum campo da cotação. A verificação SPF/DKIM/DMARC (RFC 8601) segue rodando,
 * mas como SINAL informativo no aviso, não como porteira.
 *
 * Casamento: `[COT-{referencia}]` do assunto → `cotacao_links.referencia` (ULID
 * público do link). O antigo `cotacoes.email_token` foi DESCONTINUADO: resposta a
 * e-mail enviado antes do link não casa (cai no log) — a cotação aberta ganha link
 * novo no próximo envio.
 */
class ProcessarRespostaCotacaoAction
{
    public function __construct(private VerificadorAutenticidadeEmail $verificador) {}

    /** Devolve a cotação avisada, ou null quando a mensagem é descartada. */
    public function execute(MensagemEmail $mensagem): ?Cotacao
    {
        if (! preg_match('/\[COT-([0-9A-HJKMNP-TV-Z]{26})\]/i', $mensagem->assunto, $m)) {
            Log::info('Resposta IMAP sem referência de cotação no assunto.', ['assunto' => $mensagem->assunto]);

            return null;
        }

        $link = $this->resolverLink(strtoupper($m[1]));
        if ($link === null) {
            Log::info('Resposta IMAP para referência de cotação inexistente.', ['referencia' => $m[1]]);

            return null;
        }

        return TenantContext::runFor((string) $link->tenant_id, fn () => $this->avisarNoTenant($mensagem, $link));
    }

    /**
     * A caixa IMAP é única da instalação e roda no console, SEM tenant no contexto:
     * a referência pública do link (única na base) é o lookup que DESCOBRE o tenant.
     */
    private function resolverLink(string $referencia): ?CotacaoLink
    {
        return CotacaoLink::withoutTenantScope()->where('referencia', $referencia)->first();
    }

    /**
     * TRÊS baldes por cotação/dia (limites LITERAIS em config/compras.php):
     *  - fornecedor VERDADEIRO (remetente = e-mail do cadastro E SPF/DKIM/DMARC aprovados):
     *    balde próprio, que o flood de estranhos não consome;
     *  - remetente do CADASTRO sem autenticação (fornecedor legítimo cujo domínio ainda não
     *    publica SPF/DKIM — o caso comum no varejo): balde PRÓPRIO também (COMPRAS-4r);
     *  - remetente ESTRANHO (não é o e-mail do cadastro): 1 aviso por remetente + teto baixo
     *    por cotação, que é o que fechou o flood.
     *
     * COMPRAS-4r (verificação da 4ª auditoria, sonda R4-N4): os dois últimos dividiam o
     * MESMO balde. Três estranhos com a referência pública [COT-ULID] esgotavam a cota do
     * dia e CALAVAM por 24h o aviso do fornecedor de verdade que responde sem SPF/DKIM —
     * o teto anti-flood virava uma forma de silenciar a resposta legítima. Balde separado
     * por CLASSE de remetente mantém o teto global dos estranhos sem esse efeito colateral.
     *
     * Contadores no cache POR TENANT (a chave é prefixada pelo tenant do contexto).
     */
    private function dentroDoLimiteDeAvisos(Cotacao $cotacao, string $remetente, bool $remetenteConfere, bool $autenticado): bool
    {
        $dia = now()->format('Ymd');
        $base = "compras:imap-aviso:cotacao:{$cotacao->id}:{$dia}";

        if ($remetenteConfere && $autenticado) {
            return $this->consumir("{$base}:fornecedor", (int) config('compras.cotacao_email.avisos_do_fornecedor_por_cotacao_dia', 5));
        }

        // Remetente do cadastro SEM autenticação: pode ser o fornecedor de verdade (domínio
        // sem SPF/DKIM) ou um From forjado. Não dá para distinguir — por isso o balde é
        // próprio (o estranho não o consome) e BAIXO (o forjador não inunda por ele).
        if ($remetenteConfere) {
            return $this->consumir("{$base}:fornecedor-sem-auth", (int) config('compras.cotacao_email.avisos_do_fornecedor_sem_autenticacao_por_cotacao_dia', 3));
        }

        return $this->consumir("{$base}:remetente:".hash('sha256', $remetente), (int) config('compras.cotacao_email.avisos_por_remetente_dia', 1))
            && $this->consumir("{$base}:estranhos", (int) config('compras.cotacao_email.avisos_de_estranhos_por_cotacao_dia', 3));
    }

    private function consumir(string $chave, int $maximo): bool
    {
        $cache = tenantCache();
        $cache->add($chave, 0, now()->addDay());

        return (int) $cache->increment($chave) <= $maximo;
    }

    private function avisarNoTenant(MensagemEmail $mensagem, CotacaoLink $link): ?Cotacao
    {
        $cotacao = Cotacao::query()
            // Sem usuário no console o UnidadeScope é fail-closed; a posse aqui é do link e o
            // tenant já está fixado pelo runFor (mesmo desenho do CotacaoLinkService).
            ->with(['fornecedor', 'criador', 'requisicao' => fn ($q) => $q->withoutGlobalScope(UnidadeScope::class)])
            ->find($link->cotacao_id);
        if ($cotacao === null) {
            Log::info('Resposta IMAP para cotação inexistente.', ['cotacao_id' => $link->cotacao_id]);

            return null;
        }

        // COMPRAS-4 (4ª auditoria): a referência [COT-ULID] é PÚBLICA. Cotação que já não
        // espera resposta (link revogado, cotação cancelada, requisição fora de cotação) não
        // avisa ninguém — só log.
        if ($link->revogado_em !== null
            || $cotacao->cancelada_em !== null
            || $cotacao->requisicao?->status !== StatusRequisicao::EmCotacao) {
            Log::info('Resposta IMAP ignorada (cotação não espera mais resposta).', ['cotacao_id' => $cotacao->id]);

            return null;
        }

        // Um aviso por Message-ID por tenant (a chave é prefixada pelo tenant do contexto).
        if (! tenantCache()->add('compras:imap-aviso:'.hash('sha256', $mensagem->messageId), true, now()->addDays(30))) {
            Log::info('Resposta IMAP ignorada (aviso já emitido para este Message-ID).', ['cotacao_id' => $cotacao->id]);

            return null;
        }

        $emailFornecedor = mb_strtolower(trim((string) $cotacao->fornecedor?->contato_email));
        $remetente = mb_strtolower(trim($mensagem->de));
        $remetenteConfere = $emailFornecedor !== '' && $remetente === $emailFornecedor;
        // Sinal informativo (RFC 8601): NÃO decide nada, só qualifica o aviso.
        $autenticado = $emailFornecedor !== '' && $this->verificador->autentica($mensagem, $emailFornecedor);

        // COMPRAS-4: teto de avisos ANTES da trilha e do e-mail (o flood também inundava a
        // auditoria). O dedupe por Message-ID acima é controlado pelo remetente — não basta.
        if (! $this->dentroDoLimiteDeAvisos($cotacao, $remetente, $remetenteConfere, $autenticado)) {
            Log::warning('Resposta IMAP sem aviso: limite diário de avisos da cotação atingido.', [
                'cotacao_id' => $cotacao->id,
                'remetente_confere' => $remetenteConfere,
                'autenticidade_verificada' => $autenticado,
            ]);

            return null;
        }

        app(ActivityRecorder::class)->record('compras.cotacao_resposta_email_recebida', $cotacao, null, [
            'metadata' => [
                'remetente' => $remetente,
                'remetente_confere' => $remetenteConfere,
                'autenticidade_verificada' => $autenticado,
                'message_id' => $mensagem->messageId,
            ],
        ]);

        if ($cotacao->criador?->email) {
            Mail::to($cotacao->criador->email)->send(new RespostaCotacaoPorEmailRecebida($cotacao, $remetente, $remetenteConfere, $autenticado));
        }

        Log::info('Resposta IMAP convertida em aviso ao comprador (nada gravado na cotação).', [
            'cotacao_id' => $cotacao->id,
            'remetente_confere' => $remetenteConfere,
            'autenticidade_verificada' => $autenticado,
        ]);

        return $cotacao;
    }
}
