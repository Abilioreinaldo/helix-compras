<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusPedidoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GastosCentroCusto extends Component
{
    public int $ano;

    public int $mes = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
        $this->ano = (int) now()->year;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        $resultados = DB::table('itens_pedido_compra as ipc')
            ->join('pedidos_compra as pc', 'pc.id', '=', 'ipc.pedido_compra_id')
            ->join('requisicoes as r', 'r.id', '=', 'ipc.requisicao_id')
            ->join('centros_custo as cc', 'cc.id', '=', 'r.centro_custo_id')
            ->where('pc.status', StatusPedidoCompra::Emitido->value)
            ->whereNull('pc.deleted_at')
            ->whereNull('ipc.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereNull('cc.deleted_at')
            ->whereYear('pc.emitido_em', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('pc.emitido_em', $this->mes))
            ->select(
                'cc.id',
                'cc.nome',
                'cc.codigo',
                DB::raw('SUM(ipc.valor_total) as total_gasto'),
                DB::raw('COUNT(DISTINCT pc.id) as total_pedidos'),
            )
            ->groupBy('cc.id', 'cc.nome', 'cc.codigo')
            ->orderByDesc('total_gasto')
            ->get();

        return view('livewire.relatorios.gastos-centro-custo', [
            'resultados' => $resultados,
            'totalGeral' => $resultados->sum('total_gasto'),
            'anos' => range((int) now()->year, (int) now()->year - 4),
            'meses' => [
                0 => 'Ano inteiro',
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
                4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ],
        ])->layout('components.layouts.app');
    }
}
