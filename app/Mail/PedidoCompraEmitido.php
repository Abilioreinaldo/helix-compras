<?php

namespace App\Mail;

use App\Models\PedidoCompra;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PedidoCompraEmitido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PedidoCompra $pedido
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pedido de Compra {$this->pedido->numero} emitido",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pedido-compra-emitido',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
