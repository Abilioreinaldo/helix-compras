<?php

namespace App\Models;

use Helix\Foundation\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos modelos de negócio do Compras. Carrega o escopo automático de tenant
 * da fundação (BelongsToTenant): toda consulta é filtrada pelo tenant ativo e o
 * `tenant_id` é carimbado ao criar. Esquecer o filtro vira query escopada, não
 * vazamento cross-tenant — a rede estrutural que a revisão pediu.
 *
 * Não estende esta base: `Banco` (registro COMPE global) e `User` (base da
 * fundação). Modelos que legitimamente cruzam tenants usam `withoutTenantScope()`.
 */
abstract class ComprasModel extends Model
{
    use BelongsToTenant;
}
