<?php

namespace App\Models;

use App\Enums\Perfil;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'is_compradora', 'is_financeiro', 'status', 'precisa_trocar_senha'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_compradora' => 'boolean',
            'is_financeiro' => 'boolean',
            'precisa_trocar_senha' => 'boolean',
        ];
    }

    /**
     * Unidades às quais o usuário está vinculado, com perfil e nível de alçada.
     */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'unidade_user')
            ->withPivot(['perfil', 'nivel_alcada'])
            ->withTimestamps();
    }

    /**
     * Verifica se o usuário possui o perfil informado em qualquer unidade,
     * ou se é Admin/Compradora (papéis globais representados por flags).
     */
    public function temPerfil(Perfil $perfil): bool
    {
        return match ($perfil) {
            Perfil::Admin => $this->is_admin,
            Perfil::CompradoraSenior => $this->is_compradora,
            Perfil::Financeiro => $this->is_financeiro,
            default => $this->belongsToMany(Unidade::class, 'unidade_user')
                ->withoutGlobalScopes()
                ->withPivot('perfil')
                ->wherePivot('perfil', $perfil->value)
                ->exists(),
        };
    }

    /**
     * Indica se o usuário pode visualizar todas as unidades sem restrição.
     */
    public function podeVerTodasUnidades(): bool
    {
        return $this->is_admin || $this->is_compradora;
    }

    /** Pode visualizar o módulo financeiro (contas a pagar). */
    public function podeVerPagamentos(): bool
    {
        return $this->is_financeiro || $this->is_admin;
    }

    /** Pode registrar/agendar/cancelar/reconciliar pagamentos. */
    public function podeGerenciarPagamentos(): bool
    {
        return $this->is_financeiro || $this->is_admin;
    }
}
