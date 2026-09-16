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
// `tenant_id` e `is_admin` FORA do fillable (fundação v0.2.2): são estado de
// SERVIDOR. O User não tem BelongsToTenant para carimbar o tenant sozinho, e o
// is_admin é escalação de privilégio — quem grava os dois é o UserService da
// fundação, por forceCreate/forceFill, nunca um payload de tela.
// Ver UserFactory::newModel para o caminho de teste/seed.
#[Fillable(['name', 'email', 'phone', 'password', 'status', 'precisa_trocar_senha', 'created_by', 'updated_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends \Helix\Foundation\Models\User
{
    /** @use HasFactory<UserFactory> */
    use ComprasUser, HasFactory;
}
