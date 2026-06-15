<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusRequisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ComprasEmergenciais extends Component
{
    public int $ano;

    public int $mes;

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
        $this->ano = (int) now()->year;
        $this->mes = (int) now()->month;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        // Valor por requisição usando cascata: PC emitido > cotação vencedora > estimativa.
        $resultados = DB::table('requisicoes as r')
            ->join('users as u', 'u.id', '=', 'r.solicitante_id')
            ->join('unidades as un', 'un.id', '=', 'r.unidade_id')
            ->leftJoin(
                DB::raw('(
                    SELECT ipc.requisicao_id, SUM(ipc.valor_total) AS total
                    FROM itens_pedido_compra ipc
                    INNER JOIN pedidos_compra pc ON pc.id = ipc.pedido_compra_id
                        AND pc.status = \'emitido\'
                        AND pc.deleted_at IS NULL
                    WHERE ipc.deleted_at IS NULL
                    GROUP BY ipc.requisicao_id
                ) pc_val'),
                'pc_val.requisicao_id',
                '=',
                'r.id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT requisicao_id, MAX(valor) AS valor
                    FROM cotacoes
                    WHERE vencedora = 1 AND deleted_at IS NULL
                    GROUP BY requisicao_id
                ) cot_val'),
                'cot_val.requisicao_id',
                '=',
                'r.id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT requisicao_id, SUM(quantidade * valor_unitario_estimado) AS total
                    FROM requisicao_itens
                    GROUP BY requisicao_id
                ) est_val'),
                'est_val.requisicao_id',
                '=',
                'r.id'
            )
            ->where('r.is_emergencial', true)
            ->where('r.status', '!=', StatusRequisicao::Cancelada->value)
            ->whereNull('r.deleted_at')
            ->whereYear('r.submetida_em', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('r.submetida_em', $this->mes))
            ->select(
                'r.unidade_id',
                'un.nome as unidade_nome',
                'r.solicitante_id',
                'u.name as solicitante_nome',
                DB::raw('COUNT(r.id) as total_emergenciais'),
                DB::raw('SUM(COALESCE(pc_val.total, cot_val.valor, est_val.total, 0)) as total_valor'),
            )
            ->groupBy('r.unidade_id', 'un.nome', 'r.solicitante_id', 'u.name')
            ->orderBy('un.nome')
            ->orderByDesc('total_emergenciais')
            ->get();

        return view('livewire.relatorios.compras-emergenciais', [
            'resultados' => $resultados,
            'totalEmergenciais' => $resultados->sum('total_emergenciais'),
            'totalValor' => $resultados->sum('total_valor'),
            'anos' => range((int) now()->year, (int) now()->year - 4),
            'meses' => [
                0 => 'Todos os meses',
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
                4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ],
        ])->layout('components.layouts.app');
    }
}
