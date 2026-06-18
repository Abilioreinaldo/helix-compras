<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusPedidoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ComparativoUnidades extends Component
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

        // Gasto atribuído à UNIDADE DA REQUISIÇÃO (r.unidade_id), não à do pedido de
        // compra (pc.unidade_id) — decisão R5. O join é via ipc.requisicao_id → r.
        $resultados = DB::table('itens_pedido_compra as ipc')
            ->join('pedidos_compra as pc', 'pc.id', '=', 'ipc.pedido_compra_id')
            ->join('requisicoes as r', 'r.id', '=', 'ipc.requisicao_id')
            ->join('unidades as un', function ($join) {
                $join->on('un.id', '=', 'r.unidade_id')
                    ->whereNull('un.deleted_at');
            })
            ->where('pc.status', StatusPedidoCompra::Emitido->value)
            ->whereNull('pc.deleted_at')
            ->whereNull('ipc.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereYear('pc.emitido_em', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('pc.emitido_em', $this->mes))
            ->select(
                'r.unidade_id',
                'un.nome as unidade_nome',
                DB::raw('SUM(ipc.valor_total) as total_gasto'),
                DB::raw('COUNT(DISTINCT pc.id) as total_pedidos'),
                DB::raw('COUNT(DISTINCT r.id) as total_requisicoes'),
            )
            ->groupBy('r.unidade_id', 'un.nome')
            ->orderByDesc('total_gasto')
            ->get();

        return view('livewire.relatorios.comparativo-unidades', [
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
