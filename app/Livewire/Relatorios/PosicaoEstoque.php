<?php

namespace App\Livewire\Relatorios;

use App\Models\EstoqueMinimo;
use App\Models\LoteEstoque;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosicaoEstoque extends Component
{
    public string $unidadeId = '';

    public bool $somenteAlerta = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        // Posição reusa EstoqueMinimo::posicaoEstoquePara (tombstone fundido_para_id IS NULL).
        $posicao = EstoqueMinimo::posicaoEstoquePara(auth()->user());

        if ($this->unidadeId !== '') {
            $posicao = $posicao->where('unidade_id', (int) $this->unidadeId)->values();
        }

        if ($this->somenteAlerta) {
            $posicao = $posicao->where('em_alerta', true)->values();
        }

        $unidades = DB::table('unidades')
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->select('id', 'nome')
            ->get();

        return view('livewire.relatorios.posicao-estoque', [
            'posicao' => $posicao,
            'unidades' => $unidades,
            'validades' => LoteEstoque::validadesVivasPorSaldo($posicao->pluck('saldo_id')),
            'valorTotalGeral' => $posicao->sum(fn ($linha) => (float) $linha->valor_total),
            'totalEmAlerta' => $posicao->where('em_alerta', true)->count(),
        ])->layout('components.layouts.app');
    }
}
