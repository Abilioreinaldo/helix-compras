<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusPedidoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GastosFornecedor extends Component
{
    public int $ano;

    public int $mes = 0;

    public string $agrupamento = 'fornecedor';

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
        $this->ano = (int) now()->year;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        $porCategoria = $this->agrupamento === 'categoria';

        $query = DB::table('itens_pedido_compra as ipc')
            ->join('pedidos_compra as pc', 'pc.id', '=', 'ipc.pedido_compra_id')
            ->join('fornecedores as f', 'f.id', '=', 'pc.fornecedor_id')
            ->where('pc.status', StatusPedidoCompra::Emitido->value)
            ->whereNull('pc.deleted_at')
            ->whereNull('ipc.deleted_at')
            ->whereNull('f.deleted_at')
            ->whereYear('pc.emitido_em', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('pc.emitido_em', $this->mes));

        if ($porCategoria) {
            $resultados = $query
                ->select(
                    DB::raw("COALESCE(NULLIF(f.categoria, ''), 'Sem categoria') as rotulo"),
                    DB::raw('SUM(ipc.valor_total) as total_gasto'),
                    DB::raw('COUNT(DISTINCT pc.id) as total_pedidos'),
                    DB::raw('COUNT(DISTINCT f.id) as total_fornecedores'),
                )
                ->groupBy('rotulo')
                ->orderByDesc('total_gasto')
                ->get();
        } else {
            $resultados = $query
                ->select(
                    'f.id',
                    DB::raw("COALESCE(NULLIF(f.nome_fantasia, ''), f.razao_social) as rotulo"),
                    DB::raw("COALESCE(NULLIF(f.categoria, ''), 'Sem categoria') as categoria"),
                    DB::raw('SUM(ipc.valor_total) as total_gasto'),
                    DB::raw('COUNT(DISTINCT pc.id) as total_pedidos'),
                )
                ->groupBy('f.id', 'rotulo', 'categoria')
                ->orderByDesc('total_gasto')
                ->get();
        }

        return view('livewire.relatorios.gastos-fornecedor', [
            'resultados' => $resultados,
            'porCategoria' => $porCategoria,
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
