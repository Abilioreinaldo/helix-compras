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
 * CANAL (fundação v0.7.0, decisão 13): é mensagem COMERCIAL — quem pede preço é a
 * EMPRESA, não a suíte. Sai pelo canal do tenant (`CommercialMessenger::sendEmail`), com
 * o domínio de envio dele. Nunca pelo mailer de sistema: não é (nem pode virar) um
 * `Helix\Foundation\Contracts\SystemMessage`.
 *
 * O token em claro vive só neste Mailable, e por isso ele NÃO implementa ShouldQueue —
 * enfileirá-lo por conta própria (Mail::queue) gravaria a URL com o token EM CLARO no
 * payload da fila. Quem enfileira é o `SendCommercialMessage` da fundação, que é
 * `ShouldBeEncrypted`: ali o payload inteiro vai cifrado.
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
