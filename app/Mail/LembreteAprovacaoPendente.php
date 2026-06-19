<?php

namespace App\Mail;

use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LembreteAprovacaoPendente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Requisicao $requisicao,
        public readonly User $aprovador
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Lembrete: aprovação pendente há +48h — Requisição {$this->requisicao->codigo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lembrete-aprovacao-pendente',
        );
    }
}
