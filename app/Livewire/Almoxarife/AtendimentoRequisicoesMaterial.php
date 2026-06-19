<?php

namespace App\Livewire\Almoxarife;

use App\Actions\AtenderRequisicaoMaterialAction;
use App\Actions\RecusarRequisicaoMaterialAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicaoMaterial;
use App\Models\LoteEstoque;
use App\Models\RequisicaoMaterial;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class AtendimentoRequisicoesMaterial extends Component
{
    use WithPagination;

    public ?int $recusandoId = null;

    public string $motivoRecusa = '';

    public string $erroAtendimento = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);
    }

    public function atender(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $this->erroAtendimento = '';
        $rim = RequisicaoMaterial::withoutGlobalScopes()->findOrFail($id);

        try {
            app(AtenderRequisicaoMaterialAction::class)->execute($rim, auth()->user());
            $this->dispatch('notify', mensagem: "Requisição #{$id} atendida com sucesso.");
        } catch (ValidationException $e) {
            $this->erroAtendimento = collect($e->errors())->flatten()->first() ?? 'Erro ao atender requisição.';
        }
    }

    public function abrirRecusa(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);
        $this->recusandoId = $id;
        $this->motivoRecusa = '';
        $this->erroAtendimento = '';
    }

    public function confirmarRecusa(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $this->validate(['motivoRecusa' => 'required|string|min:5'], [
            'motivoRecusa.required' => 'Informe o motivo da recusa.',
        ]);

        $rim = RequisicaoMaterial::withoutGlobalScopes()->findOrFail($this->recusandoId);

        try {
            app(RecusarRequisicaoMaterialAction::class)->execute($rim, auth()->user(), $this->motivoRecusa);
            $this->recusandoId = null;
            $this->motivoRecusa = '';
            $this->dispatch('notify', mensagem: "Requisição #{$rim->id} recusada.");
        } catch (ValidationException $e) {
            $this->erroAtendimento = collect($e->errors())->flatten()->first() ?? 'Erro ao recusar requisição.';
        }
    }

    public function cancelarRecusa(): void
    {
        $this->recusandoId = null;
        $this->motivoRecusa = '';
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $usuario = auth()->user();

        $unidadeIds = $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');

        $requisicoes = RequisicaoMaterial::with(['saldoEstoque', 'solicitante', 'unidade'])
            ->whereIn('unidade_id', $unidadeIds)
            ->where('status', StatusRequisicaoMaterial::Aberta)
            ->orderBy('created_at')
            ->paginate(15);

        $saldoIdsVencidos = LoteEstoque::saldosComLoteVencido($requisicoes->pluck('saldo_estoque_id'));

        return view('livewire.almoxarife.atendimento-requisicoes-material', compact('requisicoes', 'saldoIdsVencidos'))
            ->layout('components.layouts.app');
    }
}
