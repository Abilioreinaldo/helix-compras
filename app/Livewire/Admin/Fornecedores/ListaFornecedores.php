<?php

namespace App\Livewire\Admin\Fornecedores;

use App\Enums\Perfil;
use App\Models\Fornecedor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ListaFornecedores extends Component
{
    use WithPagination;

    public string $busca = '';

    public string $filtroAtivo = '';

    public string $filtroHomologado = '';

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    // Campos do formulário
    public string $razaoSocial = '';

    public string $nomeFantasia = '';

    public string $cnpj = '';

    public string $categoria = '';

    public string $contatoNome = '';

    public string $contatoEmail = '';

    public string $contatoTelefone = '';

    public string $observacoes = '';

    public function abrirCriar(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->razaoSocial = '';
        $this->nomeFantasia = '';
        $this->cnpj = '';
        $this->categoria = '';
        $this->contatoNome = '';
        $this->contatoEmail = '';
        $this->contatoTelefone = '';
        $this->observacoes = '';
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $this->resetValidation();
        $fornecedor = Fornecedor::findOrFail($id);
        $this->editandoId = $id;
        $this->razaoSocial = $fornecedor->razao_social;
        $this->nomeFantasia = $fornecedor->nome_fantasia ?? '';
        $this->cnpj = $fornecedor->cnpj;
        $this->categoria = $fornecedor->categoria ?? '';
        $this->contatoNome = $fornecedor->contato_nome ?? '';
        $this->contatoEmail = $fornecedor->contato_email ?? '';
        $this->contatoTelefone = $fornecedor->contato_telefone ?? '';
        $this->observacoes = $fornecedor->observacoes ?? '';
        $this->mostrarModal = true;
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $cnpjUnico = Rule::unique('fornecedores', 'cnpj')
            ->whereNull('deleted_at')
            ->when($this->editandoId, fn ($rule) => $rule->ignore($this->editandoId));

        $this->validate([
            'razaoSocial' => 'required|string|max:255',
            'nomeFantasia' => 'nullable|string|max:255',
            'cnpj' => ['required', 'string', 'size:14', $cnpjUnico],
            'categoria' => 'nullable|string|max:60',
            'contatoNome' => 'nullable|string|max:120',
            'contatoEmail' => 'nullable|email|max:150',
            'contatoTelefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ], [
            'razaoSocial.required' => 'A razão social é obrigatória.',
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.size' => 'O CNPJ deve ter 14 dígitos (somente números).',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
        ]);

        $dados = [
            'razao_social' => $this->razaoSocial,
            'nome_fantasia' => $this->nomeFantasia ?: null,
            'cnpj' => $this->cnpj,
            'categoria' => $this->categoria ?: null,
            'contato_nome' => $this->contatoNome ?: null,
            'contato_email' => $this->contatoEmail ?: null,
            'contato_telefone' => $this->contatoTelefone ?: null,
            'observacoes' => $this->observacoes ?: null,
        ];

        if ($this->editandoId) {
            Fornecedor::findOrFail($this->editandoId)->update($dados);
        } else {
            Fornecedor::create($dados);
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Fornecedor salvo com sucesso.');
    }

    public function homologar(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $fornecedor = Fornecedor::findOrFail($id);

        if ($fornecedor->homologado) {
            return;
        }

        $fornecedor->update([
            'homologado' => true,
            'homologado_em' => now(),
            'homologado_por' => auth()->id(),
        ]);

        $this->dispatch('notify', mensagem: 'Fornecedor homologado.');
    }

    public function excluir(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        Fornecedor::findOrFail($id)->delete();
        $this->dispatch('notify', mensagem: 'Fornecedor removido.');
    }

    public function render(): View
    {
        $fornecedores = Fornecedor::query()
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('razao_social', 'like', "%{$this->busca}%")
                    ->orWhere('cnpj', 'like', "%{$this->busca}%");
            }))
            ->when($this->filtroAtivo !== '', fn ($q) => $q->where('ativo', (bool) $this->filtroAtivo))
            ->when($this->filtroHomologado !== '', fn ($q) => $q->where('homologado', (bool) $this->filtroHomologado))
            ->orderBy('razao_social')
            ->paginate(15);

        return view('livewire.admin.fornecedores.lista-fornecedores', compact('fornecedores'))
            ->layout('components.layouts.app');
    }
}
