<?php

namespace App\Livewire\Almoxarife;

use App\Enums\Perfil;
use App\Models\SaldoEstoque;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SaldosEstoque extends Component
{
    use WithPagination;

    public string $busca = '';

    public string $deposito = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);
    }

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    public function updatingDeposito(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $usuario = auth()->user();

        $unidadeIds = $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');

        $query = SaldoEstoque::with('unidade')
            ->whereIn('unidade_id', $unidadeIds);

        if ($this->busca !== '') {
            $query->where('descricao_normalizada', 'like', '%'.SaldoEstoque::normalizarDescricao($this->busca).'%');
        }

        if ($this->deposito !== '') {
            $query->where('deposito', $this->deposito);
        }

        $saldos = $query->orderBy('deposito')->orderBy('descricao_item')->paginate(30);

        $depositos = SaldoEstoque::whereIn('unidade_id', $unidadeIds)
            ->distinct()
            ->orderBy('deposito')
            ->pluck('deposito');

        return view('livewire.almoxarife.saldos-estoque', compact('saldos', 'depositos'))
            ->layout('components.layouts.app');
    }
}
