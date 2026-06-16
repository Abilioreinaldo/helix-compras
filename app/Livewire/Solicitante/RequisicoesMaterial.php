<?php

namespace App\Livewire\Solicitante;

use App\Enums\Perfil;
use App\Enums\StatusRequisicaoMaterial;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class RequisicoesMaterial extends Component
{
    use WithPagination;

    public ?int $saldoEstoqueId = null;

    public string $quantidadeSolicitada = '';

    public string $justificativa = '';

    public bool $mostrarFormulario = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Solicitante), 403);
    }

    public function abrirFormulario(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Solicitante), 403);
        $this->mostrarFormulario = true;
        $this->resetForm();
    }

    public function fecharFormulario(): void
    {
        $this->mostrarFormulario = false;
        $this->resetForm();
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Solicitante), 403);

        $this->validate([
            'saldoEstoqueId' => ['required', 'integer', 'exists:saldos_estoque,id'],
            'quantidadeSolicitada' => ['required', 'numeric', 'min:0.001'],
            'justificativa' => ['required', 'string', 'min:5'],
        ], [
            'saldoEstoqueId.required' => 'Selecione o item desejado.',
            'quantidadeSolicitada.required' => 'Informe a quantidade.',
            'quantidadeSolicitada.min' => 'A quantidade deve ser maior que zero.',
            'justificativa.required' => 'Informe a justificativa.',
            'justificativa.min' => 'A justificativa deve ter ao menos 5 caracteres.',
        ]);

        $usuario = auth()->user();

        // Garante que o saldo pertence a uma unidade do solicitante
        $saldo = SaldoEstoque::whereNull('fundido_para_id')
            ->whereIn('unidade_id', $usuario->unidades()->withoutGlobalScopes()->pluck('unidades.id'))
            ->findOrFail($this->saldoEstoqueId);

        RequisicaoMaterial::create([
            'unidade_id' => $saldo->unidade_id,
            'solicitante_id' => $usuario->id,
            'saldo_estoque_id' => $saldo->id,
            'quantidade_solicitada' => $this->quantidadeSolicitada,
            'justificativa' => $this->justificativa,
            'status' => StatusRequisicaoMaterial::Aberta,
        ]);

        $this->fecharFormulario();
        $this->resetPage();
        $this->dispatch('notify', mensagem: 'Requisição aberta com sucesso.');
    }

    private function resetForm(): void
    {
        $this->saldoEstoqueId = null;
        $this->quantidadeSolicitada = '';
        $this->justificativa = '';
        $this->resetValidation();
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Solicitante), 403);

        $usuario = auth()->user();

        $requisicoes = RequisicaoMaterial::with(['saldoEstoque', 'unidade'])
            ->where('solicitante_id', $usuario->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $unidadeIds = $usuario->unidades()
            ->withoutGlobalScopes()
            ->pluck('unidades.id');

        $saldosDisponiveis = SaldoEstoque::whereIn('unidade_id', $unidadeIds)
            ->whereNull('fundido_para_id')
            ->where('quantidade', '>', 0)
            ->orderBy('descricao_item')
            ->get();

        return view('livewire.solicitante.requisicoes-material', compact('requisicoes', 'saldosDisponiveis'))
            ->layout('components.layouts.app');
    }
}
