<?php

namespace App\Livewire\Compradora;

use App\Actions\CriarRascunhoPedidoAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class GestaoPedidosCompra extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
    }

    public function criarRascunho(int $fornecedorId, array $requisicaoIds): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $fornecedor = Fornecedor::findOrFail($fornecedorId);
        $requisicoes = Requisicao::withoutGlobalScopes()
            ->whereIn('id', $requisicaoIds)
            ->get();

        try {
            $pedido = app(CriarRascunhoPedidoAction::class)->execute(
                $fornecedor,
                $requisicoes,
                auth()->user()
            );
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('acao', $mensagem);

            return;
        }

        $this->redirect(route('compradora.pedidos.editar', $pedido->id));
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        // Sugestões: requisições aprovadas sem PC emitido cobrindo todos os itens, agrupadas por fornecedor vencedor
        $sugestoes = Cotacao::withoutGlobalScopes()
            ->with(['fornecedor', 'requisicao.unidade'])
            ->where('vencedora', true)
            ->whereNull('deleted_at')
            ->whereHas('requisicao', fn ($q) => $q->withoutGlobalScopes()->where('status', StatusRequisicao::Aprovada->value))
            ->get()
            ->groupBy('fornecedor_id')
            ->map(fn ($cotacoes, $fornecedorId) => [
                'fornecedor' => $cotacoes->first()->fornecedor,
                'requisicoes' => $cotacoes->map(fn ($c) => $c->requisicao)->filter()->values(),
                'valor_total' => $cotacoes->sum('valor'),
            ])
            ->values();

        $rascunhos = PedidoCompra::withoutGlobalScopes()
            ->with(['fornecedor', 'unidade'])
            ->where('status', StatusPedidoCompra::Rascunho->value)
            ->orderByDesc('updated_at')
            ->get();

        $emitidos = PedidoCompra::withoutGlobalScopes()
            ->with(['fornecedor', 'unidade', 'emissor'])
            ->where('status', StatusPedidoCompra::Emitido->value)
            ->orderByDesc('emitido_em')
            ->paginate(15);

        return view('livewire.compradora.gestao-pedidos-compra', compact('sugestoes', 'rascunhos', 'emitidos'))
            ->layout('components.layouts.app');
    }
}
