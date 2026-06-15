<?php

namespace App\Mail;

use App\Models\PedidoCompra;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PedidoCompraRecebido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PedidoCompra $pedido
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pedido de Compra {$this->pedido->numero} recebido",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pedido-compra-recebido',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
