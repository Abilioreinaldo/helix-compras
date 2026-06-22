<?php

namespace App\Livewire;

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    /** @var array<string, string> */
    private const LABELS = [
        'rascunho' => 'Rascunho',
        'aguardando_triagem' => 'Aguardando triagem',
        'em_triagem' => 'Em triagem',
        'devolvida' => 'Devolvida',
        'em_cotacao' => 'Em cotação',
        'cotacao_concluida' => 'Cotação concluída',
        'aguardando_aprovacao' => 'Aguardando aprovação',
        'aprovada' => 'Aprovada',
        'reprovada' => 'Reprovada',
        'em_compra' => 'Em compra',
        'recebida' => 'Recebida',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ];

    public function render(): View
    {
        // Contagens por status — queries com global scope (auto-restritas à unidade do usuário).
        $statusCounts = Requisicao::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $get = fn (StatusRequisicao $s): int => (int) ($statusCounts[$s->value] ?? 0);

        $encerradas = $get(StatusRequisicao::Concluida)
            + $get(StatusRequisicao::Cancelada)
            + $get(StatusRequisicao::Reprovada)
            + $get(StatusRequisicao::Rascunho);
        $abertas = max(0, (int) $statusCounts->sum() - $encerradas);

        $pedidosEmitidos = PedidoCompra::where('status', StatusPedidoCompra::Emitido->value)->count();

        $valorEmitido = (float) PedidoCompra::where('status', StatusPedidoCompra::Emitido->value)
            ->join('itens_pedido_compra', 'itens_pedido_compra.pedido_compra_id', '=', 'pedidos_compra.id')
            ->whereNull('itens_pedido_compra.deleted_at')
            ->sum('itens_pedido_compra.valor_total');

        $valorEstoque = (float) SaldoEstoque::query()->whereNull('fundido_para_id')->sum('valor_total');

        // Pipeline (status com pelo menos 1), ordenado pela ordem natural do fluxo.
        $ordem = array_keys(self::LABELS);
        $pipeline = collect($statusCounts)
            ->reject(fn ($total, $status) => in_array($status, ['rascunho', 'cancelada', 'concluida'], true))
            ->map(fn ($total, $status) => [
                'label' => self::LABELS[$status] ?? $status,
                'total' => (int) $total,
            ])
            ->sortBy(fn ($linha, $status) => array_search($status, $ordem, true))
            ->values();
        $pipelineMax = max(1, (int) $pipeline->max('total'));

        $recentes = Requisicao::query()->with('unidade')->latest()->take(6)->get();

        return view('livewire.dashboard', [
            'abertas' => $abertas,
            'aguardandoTriagem' => $get(StatusRequisicao::AguardandoTriagem),
            'aguardandoAprovacao' => $get(StatusRequisicao::AguardandoAprovacao),
            'pedidosEmitidos' => $pedidosEmitidos,
            'valorEmitido' => $valorEmitido,
            'valorEstoque' => $valorEstoque,
            'pipeline' => $pipeline,
            'pipelineMax' => $pipelineMax,
            'recentes' => $recentes,
            'labels' => self::LABELS,
        ])->layout('components.layouts.app');
    }
}
