<?php

namespace Tests\Support;

use Helix\Foundation\Testing\Channels\FakeCommercialMailer;
use Illuminate\Mail\Mailable;

/**
 * Entrega comercial falsa do Compras (fundação v0.7.0, decisão 13).
 *
 * O `FakeCommercialMailer` do pacote guarda o REMETENTE e o HOST usados — o que prova
 * que o e-mail saiu pelo domínio do cliente e não pelo da plataforma. Aqui guardamos
 * também a INSTÂNCIA do mailable, porque os testes do Compras precisam inspecionar o
 * conteúdo da solicitação de cotação (a URL do link assinado e a referência do assunto),
 * que é justamente o que o `Mail::assertSent` fazia antes da migração de canal.
 */
class EntregaComercialDeTeste extends FakeCommercialMailer
{
    /** @var list<array{to: string, mailable: Mailable}> */
    public array $mensagens = [];

    /** @param  array<string, mixed>  $config */
    public function send(array $config, string $to, Mailable $mailable): void
    {
        parent::send($config, $to, $mailable);

        $this->mensagens[] = ['to' => $to, 'mailable' => $mailable];
    }

    /** @return list<Mailable> */
    public function paraDestinatario(string $email): array
    {
        return array_values(array_map(
            static fn (array $m): Mailable => $m['mailable'],
            array_filter($this->mensagens, static fn (array $m): bool => $m['to'] === $email),
        ));
    }
}
