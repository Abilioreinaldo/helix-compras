<?php

namespace App\Livewire\Admin\Alcadas;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ListaAlcadas extends Component
{
    use WithPagination;

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    // Campos da faixa
    public string $nome = '';

    public string $valorMinimo = '0';

    public string $valorMaximo = '';

    public bool $isEmergencial = false;

    /**
     * Etapas em edição no modal.
     *
     * @var array<int, array{nivel_exigido: string}>
     */
    public array $etapas = [];

    public function abrirCriar(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->nome = '';
        $this->valorMinimo = '0';
        $this->valorMaximo = '';
        $this->isEmergencial = false;
        $this->etapas = [];
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $this->resetValidation();
        $faixa = FaixaAlcada::with('etapas')->findOrFail($id);
        $this->editandoId = $id;
        $this->nome = $faixa->nome;
        $this->valorMinimo = (string) $faixa->valor_minimo;
        $this->valorMaximo = $faixa->valor_maximo !== null ? (string) $faixa->valor_maximo : '';
        $this->isEmergencial = $faixa->is_emergencial;
        $this->etapas = $faixa->etapas->map(fn ($e) => ['nivel_exigido' => $e->nivel_exigido->value])->toArray();
        $this->mostrarModal = true;
    }

    public function adicionarEtapa(): void
    {
        $this->etapas[] = ['nivel_exigido' => NivelAlcada::Gestor->value];
    }

    public function removerEtapa(int $indice): void
    {
        array_splice($this->etapas, $indice, 1);
        $this->etapas = array_values($this->etapas);
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $this->validate([
            'nome' => 'required|string|max:255',
            'valorMinimo' => 'required|numeric|min:0',
            'valorMaximo' => 'nullable|numeric|gt:valorMinimo',
            'isEmergencial' => 'boolean',
            'etapas' => 'array',
            'etapas.*.nivel_exigido' => ['required', 'in:'.implode(',', array_column(NivelAlcada::cases(), 'value'))],
        ], [
            'nome.required' => 'O nome é obrigatório.',
            'valorMinimo.required' => 'O valor mínimo é obrigatório.',
            'valorMaximo.gt' => 'O valor máximo deve ser maior que o mínimo.',
        ]);

        $dadosFaixa = [
            'nome' => $this->nome,
            'valor_minimo' => (float) $this->valorMinimo,
            'valor_maximo' => $this->valorMaximo !== '' ? (float) $this->valorMaximo : null,
            'is_emergencial' => $this->isEmergencial,
            'ativo' => true,
        ];

        if ($this->editandoId) {
            $faixa = FaixaAlcada::findOrFail($this->editandoId);
            $faixa->update($dadosFaixa);
            $faixa->etapas()->delete();
        } else {
            $faixa = FaixaAlcada::create($dadosFaixa);
        }

        foreach ($this->etapas as $indice => $etapa) {
            EtapaAlcada::create([
                'faixa_alcada_id' => $faixa->id,
                'ordem' => $indice + 1,
                'nivel_exigido' => $etapa['nivel_exigido'],
            ]);
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Alçada salva com sucesso.');
    }

    public function excluir(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $faixa = FaixaAlcada::findOrFail($id);
        $faixa->etapas()->delete();
        $faixa->delete();
        $this->dispatch('notify', mensagem: 'Alçada removida.');
    }

    public function render(): View
    {
        $faixas = FaixaAlcada::with('etapas')
            ->orderBy('valor_minimo')
            ->paginate(15);

        $niveisAlcada = NivelAlcada::cases();

        return view('livewire.admin.alcadas.lista-alcadas', compact('faixas', 'niveisAlcada'))
            ->layout('components.layouts.app');
    }
}
