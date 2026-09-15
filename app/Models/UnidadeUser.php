<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use InvalidArgumentException;

/**
 * Pivot `unidade_user` (vínculo usuário × unidade × perfil operacional × alçada).
 *
 * Carrega `tenant_id` — derivado SEMPRE da unidade vinculada (fonte de verdade):
 * um vínculo nunca pode apontar para unidade de outro tenant, e o pivot nunca
 * fica sem tenant (NOT NULL + unique por tenant). Como o relacionamento usa
 * `using(UnidadeUser::class)`, todo attach()/sync() passa por aqui — inclusive
 * seeders, factories e a tela de admin.
 */
class UnidadeUser extends Pivot
{
    protected $table = 'unidade_user';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (self $pivot) {
            $tenantDaUnidade = Unidade::withoutTenantScope()
                ->withoutGlobalScope(Scopes\UnidadeScope::class)
                ->whereKey($pivot->unidade_id)
                ->value('tenant_id');

            if ($tenantDaUnidade === null) {
                throw new InvalidArgumentException("Unidade {$pivot->unidade_id} inexistente ou sem tenant — vínculo recusado.");
            }

            if ($pivot->tenant_id !== null && (string) $pivot->tenant_id !== (string) $tenantDaUnidade) {
                throw new InvalidArgumentException('Vínculo usuário×unidade cruzando tenants — recusado.');
            }

            $pivot->tenant_id = $tenantDaUnidade;
        });
    }
}
