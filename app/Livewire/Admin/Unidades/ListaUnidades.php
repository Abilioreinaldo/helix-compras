<?php

namespace App\Livewire\Admin\Unidades;

use App\Enums\Perfil;
use App\Enums\StatusUnidade;
use App\Enums\TipoUnidade;
use App\Models\Obra;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ListaUnidades extends Component
{
    use WithPagination;

    public string $busca = '';

    public string $filtroTipo = '';

    public string $filtroStatus = '';

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    // Campos da unidade
    public string $nome = '';

    public string $tipo = '';

    public string $cnpj = '';

    public string $endereco = '';

    public ?int $gestorId = null;

    public string $status = 'ativa';

    // Campos de obra (visíveis somente quando tipo = obra)
    public string $obraVerba = '';

    public string $obraIniciadaEm = '';

    public string $obraPrevisaoTermino = '';

    public function abrirCriar(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->nome = '';
        $this->tipo = '';
        $this->cnpj = '';
        $this->endereco = '';
        $this->gestorId = null;
        $this->status = 'ativa';
        $this->obraVerba = '';
        $this->obraIniciadaEm = '';
        $this->obraPrevisaoTermino = '';
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $this->resetValidation();
        $unidade = Unidade::withoutGlobalScopes()->findOrFail($id);
        $this->editandoId = $id;
        $this->nome = $unidade->nome;
        $this->tipo = $unidade->tipo->value;
        $this->cnpj = $unidade->cnpj ?? '';
        $this->endereco = $unidade->endereco ?? '';
        $this->gestorId = $unidade->gestor_id;
        $this->status = $unidade->status->value;
        $this->obraVerba = '';
        $this->obraIniciadaEm = '';
        $this->obraPrevisaoTermino = '';

        if ($unidade->tipo === TipoUnidade::Obra && $unidade->obra) {
            $this->obraVerba = (string) $unidade->obra->verba;
            $this->obraIniciadaEm = $unidade->obra->iniciada_em?->format('Y-m-d') ?? '';
            $this->obraPrevisaoTermino = $unidade->obra->previsao_termino?->format('Y-m-d') ?? '';
        }

        $this->mostrarModal = true;
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $regrasObra = $this->tipo === TipoUnidade::Obra->value
            ? ['obraIniciadaEm' => 'required|date']
            : [];

        $this->validate(array_merge([
            'nome' => 'required|string|max:255',
            'tipo' => ['required', Rule::in(array_column(TipoUnidade::cases(), 'value'))],
            'cnpj' => 'nullable|string|max:14',
            'endereco' => 'nullable|string|max:500',
            'gestorId' => 'nullable|exists:users,id',
            'status' => ['required', Rule::in(array_column(StatusUnidade::cases(), 'value'))],
            'obraVerba' => 'nullable|numeric|min:0',
            'obraPrevisaoTermino' => 'nullable|date',
        ], $regrasObra), [
            'nome.required' => 'O nome é obrigatório.',
            'tipo.required' => 'O tipo é obrigatório.',
            'status.required' => 'O status é obrigatório.',
            'obraIniciadaEm.required' => 'A data de início é obrigatória para obras.',
        ]);

        $dados = [
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'cnpj' => $this->cnpj ?: null,
            'endereco' => $this->endereco ?: null,
            'gestor_id' => $this->gestorId,
            'status' => $this->status,
        ];

        if ($this->editandoId) {
            $unidade = Unidade::withoutGlobalScopes()->findOrFail($this->editandoId);
            $unidade->update($dados);

            if ($this->tipo === TipoUnidade::Obra->value) {
                $dadosObra = [
                    'iniciada_em' => $this->obraIniciadaEm ?: null,
                    'previsao_termino' => $this->obraPrevisaoTermino ?: null,
                    'verba' => $this->obraVerba !== '' ? (float) $this->obraVerba : null,
                ];
                $unidade->obra
                    ? $unidade->obra->update($dadosObra)
                    : $unidade->obra()->create(array_merge($dadosObra, ['status' => 'ativa']));
            }
        } else {
            $unidade = Unidade::withoutGlobalScopes()->create($dados);

            if ($this->tipo === TipoUnidade::Obra->value) {
                $unidade->obra()->create([
                    'iniciada_em' => $this->obraIniciadaEm ?: now()->toDateString(),
                    'previsao_termino' => $this->obraPrevisaoTermino ?: null,
                    'verba' => $this->obraVerba !== '' ? (float) $this->obraVerba : null,
                    'status' => 'ativa',
                ]);
            }
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Unidade salva com sucesso.');
    }

    public function excluir(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        Unidade::withoutGlobalScopes()->findOrFail($id)->delete();
        $this->dispatch('notify', mensagem: 'Unidade removida.');
    }

    public function render(): View
    {
        $unidades = Unidade::withoutGlobalScopes()
            ->when($this->busca, fn ($q) => $q->where('nome', 'like', "%{$this->busca}%"))
            ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
            ->when($this->filtroStatus, fn ($q) => $q->where('status', $this->filtroStatus))
            ->with(['gestor', 'obra'])
            ->orderBy('nome')
            ->paginate(15);

        $usuarios = User::orderBy('name')->get();
        $tiposUnidade = TipoUnidade::cases();
        $statusUnidade = StatusUnidade::cases();

        return view('livewire.admin.unidades.lista-unidades', compact(
            'unidades',
            'usuarios',
            'tiposUnidade',
            'statusUnidade',
        ))->layout('components.layouts.app');
    }
}
