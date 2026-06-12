<?php

namespace App\Mail;

use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequisicaoReprovada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Requisicao $requisicao,
        public readonly User $aprovador,
        public readonly string $justificativa
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Requisição {$this->requisicao->codigo} reprovada",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.requisicao-reprovada',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
