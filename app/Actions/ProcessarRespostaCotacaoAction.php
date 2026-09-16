<?php

namespace App\Actions;

use App\Imap\MensagemEmail;
use App\Imap\VerificadorAutenticidadeEmail;
use App\Mail\RespostaCotacaoPorEmailRecebida;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
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

    private function avisarNoTenant(MensagemEmail $mensagem, CotacaoLink $link): ?Cotacao
    {
        $cotacao = Cotacao::query()->with(['fornecedor', 'criador'])->find($link->cotacao_id);
        if ($cotacao === null) {
            Log::info('Resposta IMAP para cotação inexistente.', ['cotacao_id' => $link->cotacao_id]);

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
