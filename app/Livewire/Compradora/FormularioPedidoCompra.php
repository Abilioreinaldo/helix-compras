<?php

namespace App\Livewire\Compradora;

use App\Actions\CancelarPedidoCompraAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Enums\ModalidadeEntrega;
use App\Enums\Perfil;
use App\Models\PedidoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormularioPedidoCompra extends Component
{
    public int $id;

    public string $condicoesPagamento = '';

    public string $observacoes = '';

    public string $prazoEntrega = '';

    public string $modalidadeEntrega = '';

    /** @var array<int, array{id: int, descricao: string, quantidade: string, unidade_medida: string, valor_unitario: string, valor_total: string, destino: string}> */
    public array $itens = [];

    public bool $mostrarModalCancelar = false;

    public string $motivoCancelamento = '';

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status->ehEditavel(), 403);

        $this->id = $id;
        $this->condicoesPagamento = $pedido->condicoes_pagamento ?? '';
        $this->observacoes = $pedido->observacoes ?? '';
        $this->prazoEntrega = $pedido->prazo_entrega?->format('Y-m-d') ?? '';
        $this->modalidadeEntrega = $pedido->modalidade_entrega?->value ?? '';

        $this->itens = $pedido->itens->map(fn ($item) => [
            'id' => $item->id,
            'descricao' => $item->descricao,
            'quantidade' => (string) $item->quantidade,
            'unidade_medida' => $item->unidade_medida ?? '',
            'valor_unitario' => (string) $item->valor_unitario,
            'valor_total' => (string) $item->valor_total,
            'destino' => $item->destino ?? '',
        ])->toArray();
    }

    public function atualizarTotal(int $index): void
    {
        $qtd = (float) ($this->itens[$index]['quantidade'] ?? 0);
        $unit = (float) ($this->itens[$index]['valor_unitario'] ?? 0);
        $this->itens[$index]['valor_total'] = number_format($qtd * $unit, 2, '.', '');
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status->ehEditavel(), 403);

        $this->validate([
            'condicoesPagamento' => 'nullable|string|max:2000',
            'observacoes' => 'nullable|string|max:2000',
            'prazoEntrega' => 'nullable|date',
            'modalidadeEntrega' => ['nullable', Rule::enum(ModalidadeEntrega::class)],
            'itens' => 'array|min:1',
            'itens.*.valor_unitario' => 'numeric|min:0',
            'itens.*.destino' => 'nullable|string|max:255',
        ]);

        $pedido->update([
            'condicoes_pagamento' => $this->condicoesPagamento ?: null,
            'observacoes' => $this->observacoes ?: null,
            'prazo_entrega' => $this->prazoEntrega ?: null,
            'modalidade_entrega' => $this->modalidadeEntrega ?: null,
        ]);

        foreach ($this->itens as $itemData) {
            $qtd = (float) $itemData['quantidade'];
            $unit = (float) $itemData['valor_unitario'];
            $pedido->itens()->where('id', $itemData['id'])->update([
                'valor_unitario' => $unit,
                'valor_total' => $qtd * $unit,
                'destino' => $itemData['destino'] ?: null,
            ]);
        }

        session()->flash('sucesso', 'Rascunho salvo com sucesso.');
    }

    public function emitir(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->salvar();

        $pedido = $this->carregarPedido();

        try {
            app(EmitirPedidoCompraAction::class)->execute($pedido, auth()->user());
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('emissao', $mensagem);

            return;
        }

        $this->redirect(route('compradora.pedidos.index'));
    }

    public function cancelar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

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
        return PedidoCompra::withoutGlobalScopes()
            ->with(['itens', 'fornecedor', 'unidade'])
            ->findOrFail($this->id);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $pedido = $this->carregarPedido();

        return view('livewire.compradora.formulario-pedido-compra', compact('pedido'))
            ->layout('components.layouts.app');
    }
}
