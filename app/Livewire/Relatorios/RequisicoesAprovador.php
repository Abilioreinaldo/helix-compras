<?php

namespace App\Livewire\Relatorios;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RequisicoesAprovador extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        // Somente aprovações pendentes do ciclo atual de cada requisição.
        $resultados = DB::table('aprovacoes as a')
            ->join('requisicoes as r', function ($join) {
                $join->on('r.id', '=', 'a.requisicao_id')
                    ->whereColumn('a.ciclo', 'r.ciclo_aprovacao');
            })
            ->join('users as u', 'u.id', '=', 'a.aprovador_id')
            ->where('a.status', 'pendente')
            ->whereNull('a.deleted_at')
            ->whereNull('r.deleted_at')
            ->select(
                'u.id as aprovador_id',
                'u.name as aprovador_nome',
                DB::raw('COUNT(DISTINCT r.id) as total_pendentes'),
                DB::raw('MIN(r.submetida_em) as mais_antiga'),
            )
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_pendentes')
            ->get();

        return view('livewire.relatorios.requisicoes-aprovador', [
            'resultados' => $resultados,
        ])->layout('components.layouts.app');
    }
}
