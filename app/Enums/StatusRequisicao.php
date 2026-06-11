<?php

namespace App\Enums;

/** Status possíveis de uma requisição de compra no fluxo do sistema. */
enum StatusRequisicao: string
{
    case Rascunho = 'rascunho';
    case AguardandoTriagem = 'aguardando_triagem';
    case EmTriagem = 'em_triagem';
    case Devolvida = 'devolvida';
    case EmCotacao = 'em_cotacao';
    case CotacaoConcluida = 'cotacao_concluida';
    case AguardandoAprovacao = 'aguardando_aprovacao';
    case Aprovada = 'aprovada';
    case Reprovada = 'reprovada';
    case EmCompra = 'em_compra';
    case Recebida = 'recebida';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    /** Verifica se o status encerra o ciclo de vida da requisição. */
    public function ehTerminal(): bool
    {
        return in_array($this, [self::Concluida, self::Reprovada, self::Cancelada]);
    }

    /** Verifica se a requisição ainda pode ser editada pelo solicitante. */
    public function permiteEdicao(): bool
    {
        return in_array($this, [self::Rascunho, self::Devolvida]);
    }
}
