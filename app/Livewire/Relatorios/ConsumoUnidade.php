<?php

namespace App\Livewire\Relatorios;

use App\Enums\TipoMovimentacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ConsumoUnidade extends Component
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

        // Consumo = saídas de estoque (RIM). A unidade vem do saldo de origem.
        // Apenas tipo 'saida' entra — entrada/ajuste/fusão não são consumo.
        $resultados = DB::table('movimentacoes_estoque as m')
            ->join('saldos_estoque as s', 's.id', '=', 'm.saldo_estoque_id')
            ->join('unidades as u', function ($join) {
                $join->on('u.id', '=', 's.unidade_id')
                    ->whereNull('u.deleted_at');
            })
            ->where('m.tipo', TipoMovimentacao::Saida->value)
            ->whereYear('m.created_at', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('m.created_at', $this->mes))
            ->select(
                's.unidade_id',
                'u.nome as unidade_nome',
                DB::raw('COUNT(m.id) as total_saidas'),
                DB::raw('SUM(m.valor_total) as total_consumido'),
            )
            ->groupBy('s.unidade_id', 'u.nome')
            ->orderByDesc('total_consumido')
            ->get();

        return view('livewire.relatorios.consumo-unidade', [
            'resultados' => $resultados,
            'totalGeral' => $resultados->sum('total_consumido'),
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
