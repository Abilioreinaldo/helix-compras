<?php

namespace App\Actions;

use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoRecebida;
use App\Models\Cotacao;
use App\Services\ParseadorRespostaEmailService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Processa UMA mensagem de resposta de fornecedor: casa com a cotação, extrai a
 * sugestão de valor/prazo e grava nos campos ADVISORY (nunca no valor oficial).
 *
 * Camada advisory: não muda status, não escolhe vencedora, não bloqueia nada. A
 * compradora sempre confirma. Decisões registradas em Log para auditoria leve.
 */
class ProcessarRespostaCotacaoAction
{
    public function execute(MensagemEmail $mensagem): ?Cotacao
    {
        // A caixa IMAP é única da instalação e roda no console (sem tenant no contexto).
        // O casamento pelo token é o ÚNICO lookup global (o token é opaco e único na
        // base); a partir dele tudo — inclusive a idempotência — roda sob o tenant da
        // cotação. A ordem importa: a idempotência por Message-ID NÃO pode vir antes,
        // porque o Message-ID é escolhido pelo servidor do fornecedor e uma colisão
        // (acidental ou plantada) com outro tenant descartaria a resposta em silêncio.

        // 1) Casar a cotação pelo token [COT-{token}] do assunto (vindo da SolicitacaoCotacao).
        if (! preg_match('/\[COT-([0-9A-Za-z]+)\]/i', $mensagem->assunto, $m)) {
            Log::info('Resposta IMAP sem token de cotação no assunto.', ['assunto' => $mensagem->assunto]);

            return null;
        }

        $cotacaoId = $this->resolverCotacaoId($m[1]);
        if ($cotacaoId === null) {
            Log::info('Resposta IMAP para cotação inexistente.', ['token' => $m[1]]);

            return null;
        }

        $tenantId = Cotacao::withoutTenantScope()->withTrashed()->whereKey($cotacaoId)->value('tenant_id');
        if ($tenantId === null) {
            Log::info('Resposta IMAP para cotação inexistente.', ['token' => $m[1]]);

            return null;
        }

        return TenantContext::runFor((string) $tenantId, fn () => $this->processarNoTenant($mensagem, $cotacaoId));
    }

    /**
     * Id da cotação a partir do token do assunto: o formato atual é o ULID opaco
     * (`email_token`); o antigo `[COT-{id}]` numérico segue aceito por compatibilidade
     * com e-mails já em trânsito nas caixas dos fornecedores.
     */
    private function resolverCotacaoId(string $token): ?int
    {
        $id = Cotacao::withoutTenantScope()->withTrashed()->where('email_token', $token)->value('id');

        if ($id === null && ctype_digit($token)) {
            $id = Cotacao::withoutTenantScope()->withTrashed()->whereKey((int) $token)->value('id');
        }

        return $id !== null ? (int) $id : null;
    }

    /** Passos 2–7 sob o tenant da cotação (leitura/escrita/e-mail escopados). */
    private function processarNoTenant(MensagemEmail $mensagem, int $cotacaoId): ?Cotacao
    {
        // 2) Idempotência DENTRO do tenant: o mesmo e-mail nunca gera dois registros
        //    aqui, e um Message-ID repetido noutra empresa não interfere nesta.
        if (Cotacao::withTrashed()->where('email_externo_id', $mensagem->messageId)->exists()) {
            Log::info('Resposta IMAP ignorada (Message-ID já processado neste tenant).', ['message_id' => $mensagem->messageId]);

            return null;
        }

        $cotacao = Cotacao::query()->with(['fornecedor', 'criador'])->find($cotacaoId);
        if (! $cotacao) {
            Log::info('Resposta IMAP para cotação inexistente.', ['cotacao_id' => $cotacaoId]);

            return null;
        }

        // 3) Integridade leve: remetente precisa ser o e-mail do fornecedor da cotação.
        $emailFornecedor = mb_strtolower(trim((string) $cotacao->fornecedor?->contato_email));
        $remetente = mb_strtolower(trim($mensagem->de));
        if ($emailFornecedor === '' || $remetente !== $emailFornecedor) {
            Log::warning('Resposta IMAP de remetente que não confere com o fornecedor.', [
                'cotacao_id' => $cotacao->id,
                'remetente' => $remetente,
            ]);

            return null;
        }

        // 4) Rate limit: uma resposta por cotação (previne sobrescrita/spam).
        if ($cotacao->resposta_recebida_em !== null) {
            Log::info('Resposta IMAP ignorada (cotação já respondida).', ['cotacao_id' => $cotacao->id]);

            return null;
        }

        // 5) Parsear (sugestão). valor pode ser null → fallback manual (corpo fica salvo).
        $valor = ParseadorRespostaEmailService::extrairValor($mensagem->corpo);
        $prazo = ParseadorRespostaEmailService::extrairPrazo($mensagem->corpo);

        // 6) Gravar SÓ os campos advisory — nunca o valor oficial (compradora confirma).
        $cotacao->update([
            'valor_respondido' => $valor,
            'prazo_respondido' => $prazo,
            'observacoes_fornecedor' => $mensagem->corpo,
            'resposta_recebida_em' => now(),
            'email_externo_id' => $mensagem->messageId,
        ]);

        // 7) Notificar a compradora que criou a cotação.
        if ($cotacao->criador?->email) {
            Mail::to($cotacao->criador->email)->send(new RespostaCotacaoRecebida($cotacao));
        }

        Log::info('Resposta IMAP processada.', [
            'cotacao_id' => $cotacao->id,
            'valor_respondido' => $valor,
            'prazo_respondido' => $prazo,
            'parser_extraiu_valor' => $valor !== null,
        ]);

        return $cotacao;
    }
}
