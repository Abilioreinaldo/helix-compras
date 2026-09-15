<?php

namespace App\Livewire\Admin\Usuarios;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Models\Scopes\UnidadeScope;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Role;
use Helix\Foundation\Services\Platform\Identity\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Usuários do tenant, pelo admin da empresa. Papéis são os do catálogo da
 * fundação (o que cada um pode fazer se ajusta em Papéis & Permissões); o
 * vínculo unidade × perfil operacional × alçada é domínio do Compras.
 * Criação/edição passam pelo UserService (membership, papéis, evento + auditoria).
 */
class ListaUsuarios extends Component
{
    use WithPagination;

    public string $busca = '';

    public bool $mostrarModal = false;

    // Locked: identidades de usuário vêm do servidor (abrirEditar/abrirVinculos); o cliente não reaponta.
    #[Locked]
    public ?int $editandoId = null;

    public string $senhaProvisoria = '';

    // Campos do formulário principal
    public string $name = '';

    public string $email = '';

    public bool $isAdmin = false;

    /** @var array<int, string> ids dos papéis (roles) atribuídos */
    public array $papeis = [];

    public string $status = 'active';

    // Modal de vínculos
    public bool $mostrarModalVinculos = false;

    #[Locked]
    public ?int $usuarioVinculosId = null;

    public ?int $vincularUnidadeId = null;

    public string $vincularPerfil = '';

    public string $vincularNivelAlcada = '';

    public function abrirCriar(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->name = '';
        $this->email = '';
        $this->isAdmin = false;
        $this->papeis = [];
        $this->status = 'active';
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $this->resetValidation();
        $usuario = $this->usuariosDoTenant()->findOrFail($id);
        $this->editandoId = $id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->isAdmin = $usuario->is_admin;
        $this->papeis = $usuario->roles()->pluck('roles.id')->map(fn ($id) => (string) $id)->all();
        $this->status = $usuario->status;
        $this->mostrarModal = true;
    }

    public function salvar(UserService $users): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);

        $tenantId = auth()->user()->getActiveTenantId();
        $emailUnico = $this->editandoId
            ? Rule::unique('users', 'email')->ignore($this->editandoId)
            : Rule::unique('users', 'email');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', $emailUnico],
            'isAdmin' => 'boolean',
            'papeis' => 'array',
            'papeis.*' => [Rule::exists('roles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'status' => 'required|in:active,inactive',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        if ($this->editandoId) {
            $usuario = $this->usuariosDoTenant()->findOrFail($this->editandoId);
            $statusAntigo = $usuario->status;

            $users->updateUser($usuario, [
                'name' => $this->name,
                'email' => $this->email,
                'is_admin' => $this->isAdmin,
            ], $this->papeis, auth()->user());

            if ($statusAntigo !== $this->status) {
                $users->changeStatus($usuario, $this->status, auth()->user());
            }

            $this->mostrarModal = false;
            $this->dispatch('notify', mensagem: 'Usuário salvo com sucesso.');
        } else {
            $this->senhaProvisoria = Str::random(10);

            $users->createUser([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->senhaProvisoria,
                'tenant_id' => $tenantId,
                'is_admin' => $this->isAdmin,
                'status' => $this->status,
                'precisa_trocar_senha' => true,
            ], $this->papeis, auth()->user());

            $this->mostrarModal = false;
        }
    }

    public function excluir(int $id, UserService $users): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);
        $users->deleteUser($this->usuariosDoTenant()->findOrFail($id), auth()->user());
        $this->dispatch('notify', mensagem: 'Usuário removido.');
    }

    public function abrirVinculos(int $id): void
    {
        $this->usuarioVinculosId = $id;
        $this->vincularUnidadeId = null;
        $this->vincularPerfil = '';
        $this->vincularNivelAlcada = '';
        $this->mostrarModalVinculos = true;
    }

    public function adicionarVinculo(): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);

        $this->validate([
            'vincularUnidadeId' => ['required', Rule::exists('unidades', 'id')->whereNull('deleted_at')->where('tenant_id', auth()->user()->getActiveTenantId())],
            'vincularPerfil' => ['required', 'in:'.implode(',', array_map(fn (Perfil $p) => $p->value, Perfil::porUnidade()))],
            'vincularNivelAlcada' => 'nullable|in:'.implode(',', array_column(NivelAlcada::cases(), 'value')),
        ], [
            'vincularUnidadeId.required' => 'Selecione uma unidade.',
            'vincularPerfil.required' => 'Selecione um perfil.',
        ]);

        $usuario = $this->usuariosDoTenant()->findOrFail($this->usuarioVinculosId);
        $usuario->unidades()->syncWithoutDetaching([
            $this->vincularUnidadeId => [
                'perfil' => $this->vincularPerfil,
                'nivel_alcada' => $this->vincularNivelAlcada ?: null,
            ],
        ]);

        $this->vincularUnidadeId = null;
        $this->vincularPerfil = '';
        $this->vincularNivelAlcada = '';
        $this->dispatch('notify', mensagem: 'Vínculo adicionado.');
    }

    public function removerVinculo(int $unidadeId): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);
        $usuario = $this->usuariosDoTenant()->findOrFail($this->usuarioVinculosId);
        $usuario->unidades()->detach($unidadeId);
        $this->dispatch('notify', mensagem: 'Vínculo removido.');
    }

    /**
     * Base de usuários SEMPRE escopada ao tenant ativo do admin — a
     * administração de usuários nunca cruza tenants (achado C2 da revisão).
     * User não tem UnidadeScope nem BelongsToTenant: o filtro é explícito.
     */
    private function usuariosDoTenant()
    {
        return User::query()->where('tenant_id', auth()->user()->getActiveTenantId());
    }

    public function render(): View
    {
        $tenantId = auth()->user()->getActiveTenantId();

        $usuarios = $this->usuariosDoTenant()
            ->with('roles')
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('name', 'like', "%{$this->busca}%")
                    ->orWhere('email', 'like', "%{$this->busca}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        $usuarioVinculos = $this->usuarioVinculosId
            ? $this->usuariosDoTenant()->with(['unidades' => fn ($q) => $q->withoutGlobalScope(UnidadeScope::class)->where('unidades.tenant_id', $tenantId)])->find($this->usuarioVinculosId)
            : null;

        $todasUnidades = Unidade::withoutGlobalScope(UnidadeScope::class)->where('tenant_id', $tenantId)->orderBy('nome')->get();
        $papeisDisponiveis = Role::where('tenant_id', $tenantId)->orderByDesc('is_system')->orderBy('name')->get();
        $perfis = Perfil::porUnidade();
        $niveisAlcada = NivelAlcada::cases();

        return view('livewire.admin.usuarios.lista-usuarios', compact(
            'usuarios',
            'usuarioVinculos',
            'todasUnidades',
            'papeisDisponiveis',
            'perfis',
            'niveisAlcada',
        ))->layout('components.layouts.app');
    }
}
