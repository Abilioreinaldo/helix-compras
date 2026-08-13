<?php

namespace App\Livewire\Compradora;

use App\Actions\PromoverPedidoLojaAction;
use App\Enums\Perfil;
use App\Models\CentroCusto;
use App\Models\PedidoLojaRecebido;
use App\Models\Unidade;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inbox dos pedidos de compra vindos da loja (Store), via transporte inter-app
 * (ADR-015). A compradora sênior revisa o snapshot e PROMOVE a Requisicao
 * (escolhendo unidade e centro de custo — entidades que a loja não conhece) ou
 * descarta. A Requisicao nasce em rascunho e segue o fluxo normal.
 */
class PedidosLoja extends Component
{
    use WithPagination;

    public string $filtroStatus = PedidoLojaRecebido::STATUS_RECEBIDO;

    /** Pedido com o painel de promoção aberto (null = nenhum). */
    public ?int $promovendo = null;

    public ?int $unidadeId = null;

    public ?int $centroCustoId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
    }

    public function abrirPromocao(int $id): void
    {
        $this->promovendo = $id;
        $this->unidadeId = null;
        $this->centroCustoId = null;
        $this->resetErrorBag();
    }

    public function cancelarPromocao(): void
    {
        $this->promovendo = null;
    }

    public function updatedUnidadeId(): void
    {
        $this->centroCustoId = null; // centro de custo pertence à unidade
    }

    public function promover(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->validate([
            'unidadeId' => 'required|exists:unidades,id',
            'centroCustoId' => 'required|exists:centros_custo,id',
        ], [
            'unidadeId.required' => 'Escolha a unidade de destino.',
            'centroCustoId.required' => 'Escolha o centro de custo.',
        ]);

        $pedido = PedidoLojaRecebido::findOrFail($this->promovendo);

        try {
            $requisicao = app(PromoverPedidoLojaAction::class)
                ->execute($pedido, auth()->user(), $this->unidadeId, $this->centroCustoId);
        } catch (ValidationException $e) {
            $this->addError('promocao', collect($e->errors())->flatten()->first());

            return;
        }

        $this->promovendo = null;
        $this->dispatch('notify', mensagem: "Pedido {$pedido->request_code} promovido — requisição em rascunho criada.");
        $this->redirectRoute('requisicoes.editar', ['id' => $requisicao->id]);
    }

    public function descartar(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $pedido = PedidoLojaRecebido::findOrFail($id);

        if ($pedido->status !== PedidoLojaRecebido::STATUS_RECEBIDO) {
            $this->addError('promocao', 'Este pedido já foi promovido ou descartado.');

            return;
        }

        $pedido->update(['status' => PedidoLojaRecebido::STATUS_DESCARTADO]);

        // ESCOPO (D10): descarte também é ação de ciclo de vida — dual-write.
        app(ActivityRecorder::class)->record('compras.pedido_loja_descartado', $pedido, $pedido->tenant_id, [
            'actor_id' => auth()->id(),
            'metadata' => ['request_code' => $pedido->request_code, 'store_code' => $pedido->store_code],
        ]);

        $this->dispatch('notify', mensagem: "Pedido {$pedido->request_code} descartado.");
    }

    public function render(): View
    {
        $pedidos = PedidoLojaRecebido::query()
            ->when($this->filtroStatus !== '', fn ($q) => $q->where('status', $this->filtroStatus))
            ->orderByDesc('recebido_em')
            ->paginate(15);

        $unidades = Unidade::query()->orderBy('nome')->get(['id', 'nome']);
        $centrosCusto = $this->unidadeId
            ? CentroCusto::query()->where('unidade_id', $this->unidadeId)->where('ativo', true)->orderBy('nome')->get(['id', 'nome'])
            : collect();

        return view('livewire.compradora.pedidos-loja', compact('pedidos', 'unidades', 'centrosCusto'))
            ->layout('components.layouts.app');
    }
}
