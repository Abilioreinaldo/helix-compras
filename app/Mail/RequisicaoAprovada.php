<?php

namespace App\Mail;

use App\Models\Requisicao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequisicaoAprovada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Requisicao $requisicao
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Requisição {$this->requisicao->codigo} aprovada",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.requisicao-aprovada',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
