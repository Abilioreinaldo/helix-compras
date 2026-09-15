<?php

namespace App\Enums;

/**
 * Perfis do sistema de compras. Os globais (admin, compradora_senior, financeiro)
 * vêm do RBAC da fundação (flag/permissão); os operacionais são vínculo por
 * unidade (pivot unidade_user, com nível de alçada para o aprovador).
 */
enum Perfil: string
{
    case Admin = 'admin';
    case CompradoraSenior = 'compradora_senior';
    case Aprovador = 'aprovador';
    case Solicitante = 'solicitante';
    case Almoxarife = 'almoxarife';
    case Financeiro = 'financeiro';

    /** @return array<int, self> perfis que se atribuem por unidade */
    public static function porUnidade(): array
    {
        return [self::Solicitante, self::Aprovador, self::Almoxarife];
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::CompradoraSenior => 'Compradora sênior',
            self::Aprovador => 'Aprovador',
            self::Solicitante => 'Solicitante',
            self::Almoxarife => 'Almoxarife',
            self::Financeiro => 'Financeiro',
        };
    }
}
