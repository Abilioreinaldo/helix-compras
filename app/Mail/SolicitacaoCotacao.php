<?php

namespace App\Mail;

use App\Models\Cotacao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * E-mail enviado ao fornecedor solicitando a cotação (decisão 11).
 *
 * Leva o LINK ASSINADO de uso único — o único canal que grava a proposta. O assunto
 * leva `[COT-{referencia}]`, a referência PÚBLICA do link: serve só para correlacionar
 * uma eventual resposta por e-mail, que vira aviso ao comprador e nunca grava nada.
 *
 * O token em claro vive só neste Mailable: ele NÃO implementa ShouldQueue — enfileirá-lo
 * gravaria a URL com o token no payload da fila.
 */
class SolicitacaoCotacao extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cotacao $cotacao,
        public readonly string $url,
        public readonly string $referencia,
        public readonly Carbon $expiraEm,
    ) {}

    public function envelope(): Envelope
    {
        $codigo = $this->cotacao->requisicao?->codigo ?? 'requisição';

        return new Envelope(
            subject: "Solicitação de cotação [COT-{$this->referencia}] — {$codigo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.cotacao-solicitacao',
        );
    }
}
