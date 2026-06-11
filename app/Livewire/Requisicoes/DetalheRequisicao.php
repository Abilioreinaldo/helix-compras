<?php

namespace App\Livewire\Requisicoes;

use App\Actions\TransicionarStatusRequisicaoAction;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DetalheRequisicao extends Component
{
    public int $id;

    public string $motivoCancelamento = '';

    public bool $mostrarModalCancelar = false;

    public function mount(int $id): void
    {
        $this->id = $id;
    }

    public function abrirModalCancelar(): void
    {
        $this->mostrarModalCancelar = true;
    }

    public function cancelarRequisicao(): void
    {
        $this->validate(['motivoCancelamento' => 'required|string|min:5'], [
            'motivoCancelamento.required' => 'Informe o motivo do cancelamento.',
        ]);

        $requisicao = $this->carregarRequisicao();
        $requisicao->update(['motivo_cancelamento' => $this->motivoCancelamento]);

        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::Cancelada);

        $this->mostrarModalCancelar = false;
        $this->dispatch('notify', mensagem: 'Requisição cancelada.');
    }

    private function carregarRequisicao(): Requisicao
    {
        $podeVerTudo = auth()->user()->podeVerTodasUnidades();
        $query = $podeVerTudo ? Requisicao::withoutGlobalScopes() : Requisicao::query();

        return $query->with(['solicitante', 'unidade', 'centroCusto', 'obra', 'faixaAlcada.etapas', 'itens', 'logs.usuario'])->findOrFail($this->id);
    }

    public function render(): View
    {
        $requisicao = $this->carregarRequisicao();

        return view('livewire.requisicoes.detalhe-requisicao', compact('requisicao'))
            ->layout('components.layouts.app');
    }
}
