<?php

namespace App\Livewire\Compradora;

use App\Actions\TransicionarStatusRequisicaoAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TriagemRequisicoes extends Component
{
    use WithPagination;

    public string $observacaoDevolucao = '';

    public ?int $devolvendo = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
    }

    public function iniciarTriagem(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($id);
        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::EmTriagem);
        $this->dispatch('notify', mensagem: 'Triagem iniciada.');
    }

    public function enviarParaCotacao(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($id);
        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::EmCotacao);
        $this->dispatch('notify', mensagem: 'Requisição enviada para cotação.');
    }

    public function abrirDevolucao(int $id): void
    {
        $this->devolvendo = $id;
        $this->observacaoDevolucao = '';
    }

    public function confirmarDevolucao(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->validate(['observacaoDevolucao' => 'required|string|min:5'], [
            'observacaoDevolucao.required' => 'Informe o motivo da devolução.',
        ]);

        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($this->devolvendo);
        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::Devolvida, $this->observacaoDevolucao);

        $this->devolvendo = null;
        $this->observacaoDevolucao = '';
        $this->dispatch('notify', mensagem: 'Requisição devolvida ao solicitante.');
    }

    public function render(): View
    {
        $requisicoes = Requisicao::withoutGlobalScopes()
            ->with(['solicitante', 'unidade', 'centroCusto', 'itens'])
            ->whereIn('status', [StatusRequisicao::AguardandoTriagem->value, StatusRequisicao::EmTriagem->value])
            ->orderByRaw('CASE WHEN atrasada = 1 THEN 0 ELSE 1 END')
            ->orderBy('submetida_em')
            ->paginate(15);

        return view('livewire.compradora.triagem-requisicoes', compact('requisicoes'))
            ->layout('components.layouts.app');
    }
}
