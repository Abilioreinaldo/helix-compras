<?php

namespace App\Console\Commands;

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\LeitorCaixaCotacoes;
use App\Imap\LeitorCaixaCotacoesIndisponivel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lê a caixa IMAP de cotações e transforma cada resposta de fornecedor em AVISO ao
 * comprador (decisão 11: a proposta só é gravada pelo link assinado; o e-mail não
 * altera a cotação). Agendado a cada 5 min (routes/console.php, withoutOverlapping).
 */
class CapturarRespostasCotacao extends Command
{
    protected $signature = 'cotacoes:capturar-respostas';

    protected $description = 'Lê respostas de cotação da caixa IMAP e avisa o comprador (não grava proposta).';

    public function handle(LeitorCaixaCotacoes $leitor, ProcessarRespostaCotacaoAction $acao): int
    {
        // Verificação exigida sem authserv-id confiável: TODO aviso sairia marcado como
        // "autenticidade NÃO verificada" e a mensagem seria marcada como lida. Falha alto
        // e não toca a caixa (3ª auditoria; ver config mail.imap.authserv_id).
        // Sem IMAP configurado (leitor indisponível) não há caixa a proteger: segue o
        // no-op de sempre, sem falhar o scheduler de dev/CI.
        if (! $leitor instanceof LeitorCaixaCotacoesIndisponivel
            && config('mail.imap.exigir_autenticacao') !== false
            && trim((string) config('mail.imap.authserv_id')) === '') {
            Log::error('Captura IMAP de cotações sem IMAP_AUTHSERV_ID: caixa não lida.');
            $this->error('IMAP_AUTHSERV_ID não configurado: a autenticidade das respostas não pode ser verificada. Caixa não lida.');

            return self::FAILURE;
        }

        try {
            $mensagens = $leitor->naoLidas();
        } catch (\Throwable $e) {
            Log::error('Erro conectando à caixa IMAP de cotações.', ['error' => $e->getMessage()]);
            $this->error('Falha ao conectar na caixa IMAP: '.$e->getMessage());

            return self::SUCCESS; // não falha o scheduler; tenta de novo na próxima rodada
        }

        $processadas = 0;

        foreach ($mensagens as $mensagem) {
            // Auto-respostas/bounces: descarta (marca como lida para não reprocessar).
            if ($mensagem->ehAutomatica()) {
                $this->marcarComoLida($leitor, $mensagem->id);

                continue;
            }

            try {
                $cotacao = $acao->execute($mensagem);
                // Sucesso ou descarte determinístico (sem token / remetente errado): marca lida.
                $this->marcarComoLida($leitor, $mensagem->id);

                if ($cotacao !== null) {
                    $processadas++;
                }
            } catch (\Throwable $e) {
                // Erro inesperado/transitório: NÃO marca como lida → retenta na próxima rodada.
                Log::error('Erro processando resposta IMAP.', ['error' => $e->getMessage()]);
            }
        }

        $this->info("Respostas de cotação avisadas ao comprador: {$processadas}/".count($mensagens));

        return self::SUCCESS;
    }

    private function marcarComoLida(LeitorCaixaCotacoes $leitor, string $id): void
    {
        try {
            $leitor->marcarComoLida($id);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível marcar e-mail como lido.', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }
}
