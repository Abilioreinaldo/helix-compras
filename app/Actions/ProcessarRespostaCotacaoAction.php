<?php

namespace App\Actions;

use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoRecebida;
use App\Models\Cotacao;
use App\Services\ParseadorRespostaEmailService;
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
        // 1) Idempotência: o mesmo e-mail (Message-ID) nunca gera dois registros.
        if (Cotacao::withoutGlobalScopes()->where('email_externo_id', $mensagem->messageId)->exists()) {
            Log::info('Resposta IMAP ignorada (Message-ID já processado).', ['message_id' => $mensagem->messageId]);

            return null;
        }

        // 2) Casar a cotação pelo token [COT-{id}] no assunto (vindo da SolicitacaoCotacao).
        if (! preg_match('/\[COT-(\d+)\]/i', $mensagem->assunto, $m)) {
            Log::info('Resposta IMAP sem token de cotação no assunto.', ['assunto' => $mensagem->assunto]);

            return null;
        }

        $cotacao = Cotacao::withoutGlobalScopes()->with(['fornecedor', 'criador'])->find((int) $m[1]);
        if (! $cotacao) {
            Log::info('Resposta IMAP para cotação inexistente.', ['cotacao_id' => $m[1]]);

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
