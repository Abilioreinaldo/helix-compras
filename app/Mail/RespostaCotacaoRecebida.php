<?php

namespace App\Mail;

use App\Models\Cotacao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica a compradora de que um fornecedor respondeu (sugestão capturada via IMAP).
 */
class RespostaCotacaoRecebida extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cotacao $cotacao
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Resposta recebida para cotação #COT-{$this->cotacao->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.cotacao-resposta-recebida',
        );
    }
}
