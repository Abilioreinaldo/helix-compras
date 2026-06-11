<?php

namespace App\Livewire\Admin\CentrosCusto;

use App\Enums\Perfil;
use App\Models\CentroCusto;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ListaCentrosCusto extends Component
{
    use WithPagination;

    public string $busca = '';

    public string $filtroUnidade = '';

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    // Campos do formulário
    public ?int $unidadeId = null;

    public string $codigo = '';

    public string $nome = '';

    public ?int $gestorId = null;

    public bool $ativo = true;

    public function abrirCriar(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->unidadeId = null;
        $this->codigo = '';
        $this->nome = '';
        $this->gestorId = null;
        $this->ativo = true;
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $this->resetValidation();
        $centro = CentroCusto::withoutGlobalScopes()->findOrFail($id);
        $this->editandoId = $id;
        $this->unidadeId = $centro->unidade_id;
        $this->codigo = $centro->codigo;
        $this->nome = $centro->nome;
        $this->gestorId = $centro->gestor_id;
        $this->ativo = $centro->ativo;
        $this->mostrarModal = true;
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $regraCodigoUnico = Rule::unique('centros_custo', 'codigo')
            ->where('unidade_id', $this->unidadeId)
            ->whereNull('deleted_at')
            ->when($this->editandoId, fn ($rule) => $rule->ignore($this->editandoId));

        $this->validate([
            'unidadeId' => 'required|exists:unidades,id',
            'codigo' => ['required', 'string', 'max:30', $regraCodigoUnico],
            'nome' => 'required|string|max:150',
            'gestorId' => 'nullable|exists:users,id',
            'ativo' => 'boolean',
        ], [
            'unidadeId.required' => 'A unidade é obrigatória.',
            'codigo.required' => 'O código é obrigatório.',
            'codigo.unique' => 'Este código já existe nesta unidade.',
            'nome.required' => 'O nome é obrigatório.',
        ]);

        $dados = [
            'unidade_id' => $this->unidadeId,
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'gestor_id' => $this->gestorId,
            'ativo' => $this->ativo,
        ];

        if ($this->editandoId) {
            CentroCusto::withoutGlobalScopes()->findOrFail($this->editandoId)->update($dados);
        } else {
            CentroCusto::withoutGlobalScopes()->create($dados);
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Centro de custo salvo com sucesso.');
    }

    public function excluir(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        CentroCusto::withoutGlobalScopes()->findOrFail($id)->delete();
        $this->dispatch('notify', mensagem: 'Centro de custo removido.');
    }

    public function render(): View
    {
        $centros = CentroCusto::withoutGlobalScopes()
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('codigo', 'like', "%{$this->busca}%")
                    ->orWhere('nome', 'like', "%{$this->busca}%");
            }))
            ->when($this->filtroUnidade, fn ($q) => $q->where('unidade_id', $this->filtroUnidade))
            ->with(['unidade', 'gestor'])
            ->orderBy('codigo')
            ->paginate(15);

        $unidades = Unidade::withoutGlobalScopes()->orderBy('nome')->get();
        $usuarios = User::orderBy('name')->get();

        return view('livewire.admin.centros-custo.lista-centros-custo', compact('centros', 'unidades', 'usuarios'))
            ->layout('components.layouts.app');
    }
}
