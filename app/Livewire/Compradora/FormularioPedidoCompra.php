<?php

namespace App\Livewire\Compradora;

use App\Actions\CancelarPedidoCompraAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Enums\ModalidadeEntrega;
use App\Models\ItemCotacao;
use App\Models\PedidoCompra;
use App\Models\Scopes\UnidadeScope;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FormularioPedidoCompra extends Component
{
    // Locked: identidade do pedido vem do servidor (mount); o cliente não reaponta.
    #[Locked]
    public int $id;

    public string $condicoesPagamento = '';

    public string $observacoes = '';

    public string $prazoEntrega = '';

    public string $modalidadeEntrega = '';

    /**
     * Linhas do pedido — SOMENTE EXIBIÇÃO, trancadas (COMPRAS-1, 4ª auditoria).
     *
     * Era uma propriedade aberta: a compradora mandava `itens.0.quantidade = 0.01` no
     * snapshot e o `valor_total` (base do teto de alçada e do contas a pagar) saía 1000×
     * menor que o pedido real. Agora o cliente só edita `$valores` e `$destinos`, casados
     * por ÍNDICE com esta lista trancada; quantidade, descrição e id vêm do banco.
     *
     * @var array<int, array{id: int, descricao: string, quantidade: string, unidade_medida: string, preco_cotado: string|null}>
     */
    #[Locked]
    public array $itens = [];

    /** @var array<int, string> valor unitário digitado, por índice de `$itens` */
    public array $valores = [];

    /** @var array<int, string> destino digitado, por índice de `$itens` */
    public array $destinos = [];

    public bool $mostrarModalCancelar = false;

    public string $motivoCancelamento = '';

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        // `$this->id` PRIMEIRO: `carregarPedido()` lê `$this->id`, então chamá-lo antes
        // da atribuição fazia o mount depender de uma propriedade tipada ainda não
        // inicializada — o pedido carregado não era, por construção, o pedido pedido.
        $this->id = $id;

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status->ehEditavel(), 403);

        $this->condicoesPagamento = $pedido->condicoes_pagamento ?? '';
        $this->observacoes = $pedido->observacoes ?? '';
        $this->prazoEntrega = $pedido->prazo_entrega?->format('Y-m-d') ?? '';
        $this->modalidadeEntrega = $pedido->modalidade_entrega?->value ?? '';

        $cotados = ItemCotacao::whereIn('cotacao_id', $pedido->itens->pluck('cotacao_id')->filter()->unique())
            ->get()
            ->keyBy(fn (ItemCotacao $linha) => $linha->cotacao_id.':'.$linha->item_requisicao_id);

        foreach ($pedido->itens->values() as $indice => $item) {
            $cotado = $cotados->get($item->cotacao_id.':'.$item->item_requisicao_id);

            $this->itens[$indice] = [
                'id' => $item->id,
                'descricao' => $item->descricao,
                'quantidade' => (string) $item->quantidade,
                'unidade_medida' => $item->unidade_medida ?? '',
                'preco_cotado' => $cotado ? (string) $cotado->valor_unitario : null,
            ];
            $this->valores[$indice] = (string) $item->valor_unitario;
            $this->destinos[$indice] = $item->destino ?? '';
        }
    }

    /**
     * Recalcula o total exibido depois de editar um unitário. O total é derivado no
     * `render()` (quantidade do banco × unitário digitado) — aqui só se força o ciclo.
     */
    public function atualizarTotal(int $index): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status->ehEditavel(), 403);

        $this->validate([
            'condicoesPagamento' => 'nullable|string|max:2000',
            'observacoes' => 'nullable|string|max:2000',
            'prazoEntrega' => 'nullable|date',
            'modalidadeEntrega' => ['nullable', Rule::enum(ModalidadeEntrega::class)],
            'valores' => 'array',
            'valores.*' => 'required|numeric|min:0|max:99999999.99',
            'destinos' => 'array',
            'destinos.*' => 'nullable|string|max:255',
        ]);

        $pedido->update([
            'condicoes_pagamento' => $this->condicoesPagamento ?: null,
            'observacoes' => $this->observacoes ?: null,
            'prazo_entrega' => $this->prazoEntrega ?: null,
            'modalidade_entrega' => $this->modalidadeEntrega ?: null,
        ]);

        // Quantidade e identidade da linha vêm do BANCO; do cliente só o unitário e o
        // destino, casados pelo índice da lista trancada. `valor_total` é sempre derivado.
        $itensDoBanco = $pedido->itens->keyBy('id');

        foreach ($this->itens as $indice => $linha) {
            $item = $itensDoBanco->get($linha['id']);

            if ($item === null) {
                continue;
            }

            $unitario = round((float) ($this->valores[$indice] ?? 0), 2);

            $item->update([
                'valor_unitario' => $unitario,
                'valor_total' => round((float) $item->quantidade * $unitario, 2),
                'destino' => ($this->destinos[$indice] ?? '') ?: null,
            ]);
        }

        session()->flash('sucesso', 'Rascunho salvo com sucesso.');
    }

    public function emitir(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

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
            ->with(['itens', 'fornecedor', 'unidade'])
            ->findOrFail($this->id);

        // SEGUNDA TRANCA (2ª auditoria adversarial): ver DetalhePedidoCompra.
        abort_unless(auth()->user()->can('operar', $pedido), 403);

        return $pedido;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $pedido = $this->carregarPedido();

        return view('livewire.compradora.formulario-pedido-compra', compact('pedido'))
            ->layout('components.layouts.app');
    }
}
