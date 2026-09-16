<?php

namespace App\Imap;

/**
 * A resposta de cotação veio MESMO do fornecedor? (3ª auditoria adversarial)
 *
 * O `From` é texto livre; a única prova que chega com a mensagem é o carimbo
 * `Authentication-Results` do NOSSO servidor de entrada. As regras, todas
 * fail-closed (qualquer dúvida → não autenticada):
 *
 *  1. AUTHSERV-ID CONFIÁVEL (`mail.imap.authserv_id`): sem ele configurado, nada
 *     passa. Um carimbo de outro id (relay do atacante, header escrito pelo próprio
 *     remetente com um id qualquer) é ignorado.
 *  2. SÓ O PRIMEIRO do topo com esse id conta. Headers mais abaixo foram escritos
 *     antes de a mensagem chegar ao nosso MX — inclusive pelo remetente. Se esse
 *     primeiro for malformado, recusa (não "procura outro").
 *  3. DMARC: exige `dmarc=pass` com `header.from` IGUAL ao domínio do fornecedor, e
 *     nenhum resultado DMARC divergente. O DMARC é o que amarra SPF/DKIM ao From.
 *  4. SPF (por `smtp.mailfrom`, nunca só HELO) ou DKIM (`header.d`) aprovado e
 *     alinhado ao domínio do fornecedor (o domínio ou um pai dele).
 *  5. WEBMAIL PÚBLICO (`mail.imap.dominios_webmail_publico`): o domínio não
 *     identifica ninguém (qualquer um tem conta no gmail.com). Exige o E-MAIL EXATO
 *     do fornecedor em `smtp.mailfrom` (SPF) ou `header.i` (DKIM).
 *
 * Requisito de INFRA (não é código): o MTA de entrada precisa REMOVER os
 * `Authentication-Results` recebidos de fora com o nosso authserv-id (RFC 8601
 * §5) e carimbar o próprio no topo. Sem isso, uma mensagem que chegue sem o
 * carimbo do MX (falha do milter) faria o do atacante ser "o primeiro".
 */
class VerificadorAutenticidadeEmail
{
    public function autentica(MensagemEmail $mensagem, string $emailFornecedor): bool
    {
        $confiavel = mb_strtolower(trim((string) config('mail.imap.authserv_id')));
        $email = mb_strtolower(trim($emailFornecedor));
        $dominio = $this->dominioDe($email);

        if ($confiavel === '' || $dominio === '') {
            return false;
        }

        $carimbo = $this->primeiroCarimboConfiavel($mensagem->cabecalhosAutenticacao(), $confiavel);
        if ($carimbo === null) {
            return false;
        }

        if (! $this->dmarcAprovado($carimbo, $dominio)) {
            return false;
        }

        return $this->ehWebmailPublico($dominio)
            ? $this->identidadeExata($carimbo, $email)
            : $this->spfOuDkimAlinhado($carimbo, $dominio);
    }

    /** @param list<string> $cabecalhos */
    private function primeiroCarimboConfiavel(array $cabecalhos, string $confiavel): ?AuthenticationResults
    {
        foreach ($cabecalhos as $cabecalho) {
            if (AuthenticationResults::authservIdDe($cabecalho) !== $confiavel) {
                continue;
            }

            // O primeiro com o id confiável decide — malformado = recusa.
            return AuthenticationResults::parse($cabecalho);
        }

        return null;
    }

    private function dmarcAprovado(AuthenticationResults $carimbo, string $dominio): bool
    {
        $dmarc = $carimbo->doMetodo('dmarc');

        if ($dmarc === []) {
            return false;
        }

        foreach ($dmarc as $r) {
            $from = mb_strtolower(trim($r['props']['header.from'] ?? ''));

            if ($r['resultado'] !== 'pass' || $from !== $dominio) {
                return false;
            }
        }

        return true;
    }

    private function spfOuDkimAlinhado(AuthenticationResults $carimbo, string $dominio): bool
    {
        foreach ($carimbo->doMetodo('dkim') as $r) {
            if ($r['resultado'] === 'pass' && $this->alinhado(mb_strtolower(trim($r['props']['header.d'] ?? '', '. ')), $dominio)) {
                return true;
            }
        }

        foreach ($carimbo->doMetodo('spf') as $r) {
            $mailfrom = mb_strtolower(trim($r['props']['smtp.mailfrom'] ?? ''));
            $autorizador = str_contains($mailfrom, '@') ? $this->dominioDe($mailfrom) : trim($mailfrom, '. ');

            if ($r['resultado'] === 'pass' && $this->alinhado($autorizador, $dominio)) {
                return true;
            }
        }

        return false;
    }

    private function identidadeExata(AuthenticationResults $carimbo, string $email): bool
    {
        foreach ($carimbo->doMetodo('spf') as $r) {
            if ($r['resultado'] === 'pass' && mb_strtolower(trim($r['props']['smtp.mailfrom'] ?? '')) === $email) {
                return true;
            }
        }

        foreach ($carimbo->doMetodo('dkim') as $r) {
            if ($r['resultado'] === 'pass' && mb_strtolower(trim($r['props']['header.i'] ?? '')) === $email) {
                return true;
            }
        }

        return false;
    }

    /** O autorizador é o próprio domínio ou um PAI dele — nunca um subdomínio. */
    private function alinhado(string $autorizador, string $esperado): bool
    {
        return $autorizador !== ''
            && ($autorizador === $esperado || str_ends_with($esperado, '.'.$autorizador));
    }

    private function ehWebmailPublico(string $dominio): bool
    {
        $publicos = array_map(
            fn ($d) => mb_strtolower(trim((string) $d)),
            (array) config('mail.imap.dominios_webmail_publico', []),
        );

        return in_array($dominio, $publicos, true);
    }

    private function dominioDe(string $email): string
    {
        $arroba = strrchr($email, '@');

        return $arroba === false ? '' : trim(substr($arroba, 1), '. ');
    }
}
