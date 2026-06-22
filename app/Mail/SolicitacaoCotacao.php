<?php

namespace App\Mail;

use App\Models\Cotacao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail enviado ao fornecedor solicitando a cotação. O token [COT-{id}] no assunto
 * é o que permite casar a RESPOSTA do fornecedor com esta cotação na captura IMAP.
 */
class SolicitacaoCotacao extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cotacao $cotacao
    ) {}

    public function envelope(): Envelope
    {
        $codigo = $this->cotacao->requisicao?->codigo ?? 'requisição';

        return new Envelope(
            subject: "Solicitação de cotação [COT-{$this->cotacao->id}] — {$codigo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.cotacao-solicitacao',
        );
    }
}
