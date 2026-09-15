<?php

namespace App\Livewire\Almoxarife;

use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Models\PedidoCompra;
use App\Models\Scopes\UnidadeScope;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class GestaoPedidosRecebimento extends Component
{
    use AuthorizesRequests, WithPagination;

    public function mount(): void
    {
        $this->authorize('estoque.gerenciar');
    }

    public function render(): View
    {
        $this->authorize('estoque.gerenciar');

        $usuario = auth()->user();

        $unidadeIds = $usuario->unidades()
            ->withoutGlobalScope(UnidadeScope::class)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');

        $pedidos = PedidoCompra::withoutGlobalScope(UnidadeScope::class)
            ->with(['fornecedor', 'unidade'])
            ->where('status', StatusPedidoCompra::Emitido->value)
            ->whereIn('unidade_id', $unidadeIds)
            ->orderByDesc('emitido_em')
            ->paginate(20);

        return view('livewire.almoxarife.gestao-pedidos-recebimento', compact('pedidos'))
            ->layout('components.layouts.app');
    }
}
