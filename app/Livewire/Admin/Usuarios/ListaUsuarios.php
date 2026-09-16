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
use Illuminate\Support\Facades\DB;
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
        abort_unless(auth()->user()->can('users.manage'), 403);
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
        abort_unless(auth()->user()->can('users.manage'), 403);
        $this->resetValidation();
        $usuario = $this->usuariosDoTenant()->findOrFail($id);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        // Identidade é COMPARTILHADA na suíte: editar nome/e-mail/status/papéis de um
        // convidado (home noutro tenant) escreveria no tenant DELE. Ver notaGuest().
        abort_if($this->ehConvidado($usuario), 403, 'Usuário convidado de outro tenant: gerencie apenas o vínculo.');
        $tenantId = auth()->user()->getActiveTenantId();

        $this->editandoId = $id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        // Admin é propriedade do TENANT (pivot tenant_user.is_admin), não a coluna global.
        $this->isAdmin = $this->ehAdminNoTenant($usuario, $tenantId);
        $this->papeis = $usuario->roles()
            ->wherePivot('tenant_id', $tenantId)
            ->pluck('roles.id')->map(fn ($id) => (string) $id)->all();
        $this->status = $usuario->status;
        $this->mostrarModal = true;
    }

    public function salvar(UserService $users): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

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
            abort_unless(auth()->user()->can('operar', $usuario), 403);
            abort_if($this->ehConvidado($usuario), 403, 'Usuário convidado de outro tenant: gerencie apenas o vínculo.');
            $statusAntigo = $usuario->status;

            $users->updateUser($usuario, [
                'name' => $this->name,
                'email' => $this->email,
                'is_admin' => $this->isAdmin,
            ], $this->papeis, auth()->user());

            // `status` mora na IDENTIDADE (users.status), não na membership: inativar
            // aqui derrubaria o acesso do usuário em TODOS os tenants dele. Só é
            // permitido quando este tenant é o único vínculo — caso contrário, o
            // caminho correto é remover o vínculo (excluir). Falta na fundação um
            // "suspender membership" (tenant_user.status) para permitir o resto.
            if ($statusAntigo !== $this->status) {
                if ($this->temOutroVinculo($usuario)) {
                    $this->addError('status', 'Este usuário também participa de outra empresa: inativá-lo aqui derrubaria o acesso dele lá. Remova o vínculo com esta empresa.');

                    return;
                }

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

    /**
     * Remove o usuário DESTA empresa. Se ele participa de outra, só o VÍNCULO com
     * este tenant cai (identidade e acesso nos demais ficam intactos); se este é o
     * único vínculo, a identidade é removida como antes.
     */
    public function excluir(int $id, UserService $users): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

        $usuario = $this->usuariosDoTenant()->findOrFail($id);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        $tenantId = auth()->user()->getActiveTenantId();

        if ($this->temOutroVinculo($usuario)) {
            // Vínculos por unidade deste tenant caem junto (o pivot é escopado).
            DB::table('unidade_user')
                ->where('user_id', $usuario->getKey())
                ->where('tenant_id', $tenantId)
                ->delete();

            $users->removeMembership($usuario, (string) $tenantId, auth()->user());
            $this->dispatch('notify', mensagem: 'Usuário removido desta empresa (segue ativo nas demais).');

            return;
        }

        $users->deleteUser($usuario, auth()->user());
        $this->dispatch('notify', mensagem: 'Usuário removido.');
    }

    public function abrirVinculos(int $id): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        // Anti-IDOR: o usuário alvo precisa ser do tenant ativo.
        $usuario = $this->usuariosDoTenant()->findOrFail($id);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        $this->usuarioVinculosId = $usuario->id;
        $this->vincularUnidadeId = null;
        $this->vincularPerfil = '';
        $this->vincularNivelAlcada = '';
        $this->mostrarModalVinculos = true;
    }

    public function adicionarVinculo(): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

        $this->validate([
            'vincularUnidadeId' => ['required', Rule::exists('unidades', 'id')->whereNull('deleted_at')->where('tenant_id', auth()->user()->getActiveTenantId())],
            'vincularPerfil' => ['required', 'in:'.implode(',', array_map(fn (Perfil $p) => $p->value, Perfil::porUnidade()))],
            'vincularNivelAlcada' => 'nullable|in:'.implode(',', array_column(NivelAlcada::cases(), 'value')),
        ], [
            'vincularUnidadeId.required' => 'Selecione uma unidade.',
            'vincularPerfil.required' => 'Selecione um perfil.',
        ]);

        $usuario = $this->usuariosDoTenant()->findOrFail($this->usuarioVinculosId);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
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
        abort_unless(auth()->user()->can('users.manage'), 403);
        $usuario = $this->usuariosDoTenant()->findOrFail($this->usuarioVinculosId);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        $usuario->unidades()->detach($unidadeId);
        $this->dispatch('notify', mensagem: 'Vínculo removido.');
    }

    /**
     * Base de usuários do tenant ativo pela MEMBERSHIP (pivot `tenant_user`), não
     * por `users.tenant_id` — que é só o tenant HOME da identidade compartilhada.
     *
     * Filtrar pelo home errava nas duas direções: quem tem home aqui mas já teve o
     * vínculo revogado continuava administrável (e uma inativação derrubava o acesso
     * dele nas outras empresas), e quem trabalha aqui com home noutra empresa ficava
     * INVISÍVEL para o admin desta. Membership ativa é a única definição de "é gente
     * desta empresa" — a mesma que a autorização usa (User::belongsToTenant).
     */
    private function usuariosDoTenant()
    {
        $tenantId = auth()->user()->getActiveTenantId();

        return User::query()->whereExists(fn ($q) => $q
            ->selectRaw('1')
            ->from('tenant_user')
            ->whereColumn('tenant_user.user_id', 'users.id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->where('tenant_user.status', 'active'));
    }

    /** O usuário é CONVIDADO aqui? (participa deste tenant, mas sua identidade mora noutro). */
    private function ehConvidado(User $usuario): bool
    {
        return (string) ($usuario->getAttributes()['tenant_id'] ?? '') !== (string) auth()->user()->getActiveTenantId();
    }

    /** O usuário participa de algum tenant ALÉM do ativo (vínculo ativo)? */
    private function temOutroVinculo(User $usuario): bool
    {
        return DB::table('tenant_user')
            ->where('user_id', $usuario->getKey())
            ->where('tenant_id', '!=', auth()->user()->getActiveTenantId())
            ->where('status', 'active')
            ->exists();
    }

    /** Admin DESTE tenant (pivot), não a coluna global `users.is_admin`. */
    private function ehAdminNoTenant(User $usuario, ?string $tenantId): bool
    {
        return DB::table('tenant_user')
            ->where('user_id', $usuario->getKey())
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('is_admin', true)
            ->exists();
    }

    public function render(): View
    {
        $tenantId = auth()->user()->getActiveTenantId();

        $usuarios = $this->usuariosDoTenant()
            // Papéis são POR TENANT (pivot user_role.tenant_id): sem o filtro, a tela
            // mostraria a um admin daqui os papéis que o convidado tem na empresa dele.
            ->with(['roles' => fn ($q) => $q->where('user_role.tenant_id', $tenantId)])
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('name', 'like', "%{$this->busca}%")
                    ->orWhere('email', 'like', "%{$this->busca}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        // Admin e "convidado" são propriedades do VÍNCULO com este tenant, lidas do pivot.
        $idsPagina = $usuarios->pluck('id')->all();
        $adminsDoTenant = DB::table('tenant_user')
            ->whereIn('user_id', $idsPagina)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('is_admin', true)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $convidados = $usuarios->filter(fn (User $u) => $this->ehConvidado($u))->pluck('id')->all();

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
            'adminsDoTenant',
            'convidados',
        ))->layout('components.layouts.app');
    }
}
