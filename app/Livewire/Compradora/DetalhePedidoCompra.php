<?php

namespace App\Livewire\Compradora;

use App\Actions\CancelarPedidoCompraAction;
use App\Models\PedidoCompra;
use App\Models\Scopes\UnidadeScope;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DetalhePedidoCompra extends Component
{
    // Locked: identidade do pedido vem do servidor (mount); o cliente não reaponta.
    #[Locked]
    public int $id;

    public bool $mostrarModalCancelar = false;

    public string $motivoCancelamento = '';

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->id = $id;
    }

    public function cancelar(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $pedido = $this->carregarPedido();

        try {
            app(CancelarPedidoCompraAction::class)->execute($pedido, auth()->user(), $this->motivoCancelamento);
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('cancelamento', $mensagem);
            $this->mostrarModalCancelar = false;

            return;
        }

        $this->redirect(route('compradora.pedidos.index'));
    }

    private function carregarPedido(): PedidoCompra
    {
        $pedido = PedidoCompra::withoutGlobalScope(UnidadeScope::class)
            ->with(['itens.requisicao', 'fornecedor', 'unidade', 'emissor'])
            ->findOrFail($this->id);

        // SEGUNDA TRANCA (2ª auditoria adversarial): o `withoutGlobalScope` acima
        // dispensa o filtro de UNIDADE, e o de tenant é filtro de CONSULTA — a posse do
        // registro tem de ser decidida pela policy, que é a única que recebe o registro.
        // Aqui, e não no mount: assim vale para o mount, para as actions e para o render.
        abort_unless(auth()->user()->can('operar', $pedido), 403);

        return $pedido;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $pedido = $this->carregarPedido();

        return view('livewire.compradora.detalhe-pedido-compra', compact('pedido'))
            ->layout('components.layouts.app');
    }
}
