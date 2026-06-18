<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusPedidoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CustoObra extends Component
{
    public int $ano;

    public string $obraId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
        $this->ano = (int) now()->year;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        // Lista de obras disponíveis para o filtro (unidade.nome como nome da obra).
        $obras = DB::table('obras as o')
            ->join('unidades as un', 'un.id', '=', 'o.unidade_id')
            ->whereNull('un.deleted_at')
            ->orderBy('un.nome')
            ->select('o.id', 'un.nome')
            ->get();

        // Custo comprometido (PC emitido) por obra × mês no ano selecionado.
        // Extração do mês é driver-aware: strftime (SQLite) não existe no MySQL.
        // Ambos retornam '01'–'12' zero-padded, então o (int) $linha->mes a jusante vale.
        $driver = DB::getDriverName();
        $mesExpr = ($driver === 'mysql' || $driver === 'mariadb')
            ? "DATE_FORMAT(pc.emitido_em, '%m')"
            : "strftime('%m', pc.emitido_em)";

        $movimentos = DB::table('itens_pedido_compra as ipc')
            ->join('pedidos_compra as pc', 'pc.id', '=', 'ipc.pedido_compra_id')
            ->join('requisicoes as r', 'r.id', '=', 'ipc.requisicao_id')
            ->join('obras as o', 'o.id', '=', 'r.obra_id')
            ->join('unidades as un', 'un.id', '=', 'o.unidade_id')
            ->where('pc.status', StatusPedidoCompra::Emitido->value)
            ->whereNotNull('r.obra_id')
            ->whereNull('pc.deleted_at')
            ->whereNull('ipc.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereYear('pc.emitido_em', $this->ano)
            ->when($this->obraId !== '', fn ($q) => $q->where('o.id', (int) $this->obraId))
            ->select(
                'o.id as obra_id',
                'o.verba',
                'un.nome as obra_nome',
                DB::raw("{$mesExpr} as mes"),
                DB::raw('SUM(ipc.valor_total) as total_mes'),
            )
            ->groupBy('o.id', 'o.verba', 'un.nome', DB::raw($mesExpr))
            ->orderBy('un.nome')
            ->orderBy('mes')
            ->get();

        // Agrupa por obra e monta curva mensal (índice 01–12).
        $curvas = $movimentos->groupBy('obra_id')->map(function ($linhas) {
            $first = $linhas->first();
            $mensal = array_fill(1, 12, 0.0);

            foreach ($linhas as $linha) {
                $mensal[(int) $linha->mes] = (float) $linha->total_mes;
            }

            $totalAno = array_sum($mensal);
            $acumulado = [];
            $soma = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $soma += $mensal[$m];
                $acumulado[$m] = $soma;
            }

            $verba = $first->verba !== null ? (float) $first->verba : null;

            return [
                'obra_id' => $first->obra_id,
                'obra_nome' => $first->obra_nome,
                'verba' => $verba,
                'mensal' => $mensal,
                'acumulado' => $acumulado,
                'total_ano' => $totalAno,
                'percentual_verba' => $verba > 0 ? round(($totalAno / $verba) * 100, 1) : null,
            ];
        })->values();

        return view('livewire.relatorios.custo-obra', [
            'curvas' => $curvas,
            'obras' => $obras,
            'anos' => range((int) now()->year, (int) now()->year - 4),
            'mesesAbrev' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
        ])->layout('components.layouts.app');
    }
}
