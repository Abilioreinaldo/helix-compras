<?php

namespace App\Livewire\Almoxarife;

use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Models\PedidoCompra;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GestaoPedidosRecebimento extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $usuario = auth()->user();

        $unidadeIds = $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');

        $pedidos = PedidoCompra::withoutGlobalScopes()
            ->with(['fornecedor', 'unidade'])
            ->where('status', StatusPedidoCompra::Emitido->value)
            ->whereIn('unidade_id', $unidadeIds)
            ->orderByDesc('emitido_em')
            ->paginate(20);

        return view('livewire.almoxarife.gestao-pedidos-recebimento', compact('pedidos'))
            ->layout('components.layouts.app');
    }
}
