<?php

namespace App\Livewire\Admin\CatalogoItens;

use App\Actions\ConfirmarVinculoSaldoAction;
use App\Actions\SugerirVinculoCatalogoAction;
use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\SaldoEstoque;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ReconciliacaoSaldos extends Component
{
    use WithPagination;

    public string $buscaManual = '';

    public ?int $saldoSelecionadoId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
    }

    public function abrirVinculoManual(int $saldoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $this->saldoSelecionadoId = $saldoId;
        $this->buscaManual = '';
    }

    public function fecharVinculoManual(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $this->saldoSelecionadoId = null;
        $this->buscaManual = '';
    }

    public function vincular(int $saldoId, int $itemCatalogoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $saldo = SaldoEstoque::findOrFail($saldoId);
        $item = CatalogoItem::findOrFail($itemCatalogoId);

        try {
            app(ConfirmarVinculoSaldoAction::class)->vincular($saldo, $item, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('vinculo', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        $this->saldoSelecionadoId = null;
        $this->dispatch('notify', mensagem: 'Saldo vinculado ao item de catálogo.');
    }

    public function desvincular(int $saldoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $saldo = SaldoEstoque::findOrFail($saldoId);

        app(ConfirmarVinculoSaldoAction::class)->desvincular($saldo, auth()->user());

        $this->dispatch('notify', mensagem: 'Vínculo removido.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $saldos = SaldoEstoque::with('unidade')
            ->whereNull('item_catalogo_id')
            ->orderBy('deposito')
            ->orderBy('descricao_item')
            ->paginate(15);

        $sugerirAction = app(SugerirVinculoCatalogoAction::class);
        $sugestoes = [];
        foreach ($saldos as $saldo) {
            $sugestoes[$saldo->id] = $sugerirAction->execute($saldo)->take(5);
        }

        $itensBuscaManual = collect();
        if ($this->buscaManual !== '') {
            $itensBuscaManual = CatalogoItem::where('ativo', true)
                ->where(function ($q) {
                    $q->where('descricao', 'like', "%{$this->buscaManual}%")
                        ->orWhere('codigo', 'like', "%{$this->buscaManual}%");
                })
                ->orderBy('descricao')
                ->limit(20)
                ->get();
        }

        return view('livewire.admin.catalogo-itens.reconciliacao-saldos', compact('saldos', 'sugestoes', 'itensBuscaManual'))
            ->layout('components.layouts.app');
    }
}
