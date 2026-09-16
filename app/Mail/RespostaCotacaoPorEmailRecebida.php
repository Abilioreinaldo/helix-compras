<?php

namespace App\Mail;

use App\Models\Cotacao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * AVISO ao comprador: chegou um e-mail de resposta referente à cotação (decisão 11).
 * Nada foi gravado — a proposta só vale pelo link assinado. Os sinais (remetente
 * confere / SPF-DKIM-DMARC pelo RFC 8601) são INFORMATIVOS, para o comprador conferir.
 */
class RespostaCotacaoPorEmailRecebida extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cotacao $cotacao,
        public readonly string $remetente,
        public readonly bool $remetenteConfere,
        public readonly bool $autenticado,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Resposta por e-mail recebida — conferir cotação #COT-{$this->cotacao->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.cotacao-resposta-email-aviso',
        );
    }
}
