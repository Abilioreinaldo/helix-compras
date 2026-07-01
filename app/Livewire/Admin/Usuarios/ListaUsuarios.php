<?php

namespace App\Livewire\Admin\Usuarios;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ListaUsuarios extends Component
{
    use WithPagination;

    public string $busca = '';

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $senhaProvisoria = '';

    // Campos do formulário principal
    public string $name = '';

    public string $email = '';

    public bool $isAdmin = false;

    public bool $isCompradora = false;

    public string $status = 'ativo';

    // Modal de vínculos
    public bool $mostrarModalVinculos = false;

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
        $this->isCompradora = false;
        $this->status = 'ativo';
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $this->resetValidation();
        $usuario = User::withoutGlobalScopes()->findOrFail($id);
        $this->editandoId = $id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->isAdmin = $usuario->is_admin;
        $this->isCompradora = $usuario->hasRole('compras');
        $this->status = $usuario->status;
        $this->mostrarModal = true;
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $emailUnico = $this->editandoId
            ? Rule::unique('users', 'email')->ignore($this->editandoId)
            : Rule::unique('users', 'email');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', $emailUnico],
            'isAdmin' => 'boolean',
            'isCompradora' => 'boolean',
            'status' => 'required|in:ativo,inativo',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        if ($this->editandoId) {
            User::withoutGlobalScopes()->findOrFail($this->editandoId)->update([
                'name' => $this->name,
                'email' => $this->email,
                'is_admin' => $this->isAdmin,
                'status' => $this->status,
            ]);
            // TODO(P3): atribuir/retirar o papel 'compras' via roles() conforme $this->isCompradora.
            $this->mostrarModal = false;
            $this->dispatch('notify', mensagem: 'Usuário salvo com sucesso.');
        } else {
            $this->senhaProvisoria = Str::random(10);
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => bcrypt($this->senhaProvisoria),
                'tenant_id' => auth()->user()->tenant_id,
                'is_admin' => $this->isAdmin,
                'status' => $this->status,
                'precisa_trocar_senha' => true,
            ]);
            // TODO(P3): atribuir o papel 'compras' via roles() conforme $this->isCompradora.
            $this->mostrarModal = false;
        }
    }

    public function excluir(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        User::withoutGlobalScopes()->findOrFail($id)->delete();
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
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $this->validate([
            'vincularUnidadeId' => ['required', Rule::exists('unidades', 'id')->whereNull('deleted_at')],
            'vincularPerfil' => ['required', 'in:'.implode(',', array_column(Perfil::cases(), 'value'))],
            'vincularNivelAlcada' => 'nullable|in:'.implode(',', array_column(NivelAlcada::cases(), 'value')),
        ], [
            'vincularUnidadeId.required' => 'Selecione uma unidade.',
            'vincularPerfil.required' => 'Selecione um perfil.',
        ]);

        $usuario = User::withoutGlobalScopes()->findOrFail($this->usuarioVinculosId);
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
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $usuario = User::withoutGlobalScopes()->findOrFail($this->usuarioVinculosId);
        $usuario->unidades()->detach($unidadeId);
        $this->dispatch('notify', mensagem: 'Vínculo removido.');
    }

    public function render(): View
    {
        $usuarios = User::withoutGlobalScopes()
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('name', 'like', "%{$this->busca}%")
                    ->orWhere('email', 'like', "%{$this->busca}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        $usuarioVinculos = $this->usuarioVinculosId
            ? User::withoutGlobalScopes()->with(['unidades' => fn ($q) => $q->withoutGlobalScopes()])->find($this->usuarioVinculosId)
            : null;

        $todasUnidades = Unidade::withoutGlobalScopes()->orderBy('nome')->get();
        $perfis = Perfil::cases();
        $niveisAlcada = NivelAlcada::cases();

        return view('livewire.admin.usuarios.lista-usuarios', compact(
            'usuarios',
            'usuarioVinculos',
            'todasUnidades',
            'perfis',
            'niveisAlcada',
        ))->layout('components.layouts.app');
    }
}
