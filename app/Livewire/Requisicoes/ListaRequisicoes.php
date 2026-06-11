<?php

namespace App\Livewire\Requisicoes;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ListaRequisicoes extends Component
{
    use WithPagination;

    public string $filtroStatus = '';

    public bool $filtroUrgente = false;

    public bool $filtroAtrasada = false;

    public function render(): View
    {
        $podeVerTudo = auth()->user()->podeVerTodasUnidades();

        $requisicoes = ($podeVerTudo ? Requisicao::withoutGlobalScopes() : Requisicao::query())
            ->with(['solicitante', 'unidade', 'centroCusto'])
            ->when($this->filtroStatus, fn ($q) => $q->where('status', $this->filtroStatus))
            ->when($this->filtroUrgente, fn ($q) => $q->where('urgente', true))
            ->when($this->filtroAtrasada, fn ($q) => $q->where('atrasada', true))
            ->when(! $podeVerTudo, fn ($q) => $q->where('solicitante_id', auth()->id()))
            ->orderByDesc('created_at')
            ->paginate(15);

        $statusDisponiveis = StatusRequisicao::cases();

        return view('livewire.requisicoes.lista-requisicoes', compact('requisicoes', 'statusDisponiveis'))
            ->layout('components.layouts.app');
    }
}
