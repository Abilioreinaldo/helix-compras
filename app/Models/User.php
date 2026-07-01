<?php

namespace App\Models;

use App\Models\Concerns\ComprasUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * User do app de Compras. Estende a base de identidade compartilhada da fundação
 * (tabela `users` + RBAC/tenant/2FA vindos da HELIX) e adiciona o domínio de
 * Compras via trait (unidades, perfis operacionais, papéis compras/financeiro).
 */
class User extends \Helix\Foundation\Models\User
{
    /** @use HasFactory<UserFactory> */
    use ComprasUser, HasFactory;
}
