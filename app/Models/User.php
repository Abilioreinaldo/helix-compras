<?php

namespace App\Models;

use App\Models\Concerns\ComprasUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * User do app de Compras. Estende a base de identidade compartilhada da fundação
 * (tabela `users` + RBAC/tenant/2FA vindos da HELIX) e adiciona o domínio de
 * Compras via trait (unidades, perfis operacionais, papéis compras/financeiro).
 */
#[Fillable(['name', 'email', 'phone', 'password', 'tenant_id', 'status', 'is_admin', 'precisa_trocar_senha'])]
#[Hidden(['password', 'remember_token'])]
class User extends \Helix\Foundation\Models\User
{
    /** @use HasFactory<UserFactory> */
    use ComprasUser, HasFactory;
}
