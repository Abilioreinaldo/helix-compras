<?php

namespace App\Console\Commands;

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Imap\LeitorCaixaCotacoes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lê a caixa IMAP de cotações e registra as respostas dos fornecedores (advisory).
 * Agendado a cada 5 min (routes/console.php, withoutOverlapping).
 */
class CapturarRespostasCotacao extends Command
{
    protected $signature = 'cotacoes:capturar-respostas';

    protected $description = 'Captura respostas de cotação da caixa IMAP e registra a sugestão de valor/prazo.';

    public function handle(LeitorCaixaCotacoes $leitor, ProcessarRespostaCotacaoAction $acao): int
    {
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

        $this->info("Respostas de cotação processadas: {$processadas}/".count($mensagens));

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
