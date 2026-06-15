<div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-2">{{ $pedido->numero ? 'Pedido ' . $pedido->numero : 'Rascunho de Pedido de Compra' }}</h1>
    <p class="text-gray-600 mb-6">Fornecedor: <strong>{{ $pedido->fornecedor->razao_social }}</strong></p>

    @if(session('sucesso'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">{{ session('sucesso') }}</div>
    @endif

    @error('emissao')
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{{ $message }}</div>
    @enderror

    @error('cancelamento')
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{{ $message }}</div>
    @enderror

    <div class="mb-6 grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Condições de Pagamento</label>
            <textarea wire:model="condicoesPagamento" rows="2" class="w-full border rounded p-2 text-sm"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Observações</label>
            <textarea wire:model="observacoes" rows="2" class="w-full border rounded p-2 text-sm"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Prazo de Entrega</label>
            <input type="date" wire:model="prazoEntrega" class="w-full border rounded p-2 text-sm" />
            @error('prazoEntrega') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Modalidade de Entrega</label>
            <select wire:model="modalidadeEntrega" class="w-full border rounded p-2 text-sm">
                <option value="">— Selecione —</option>
                <option value="entrega">Entrega pelo fornecedor</option>
                <option value="retirada">Retirada pelo comprador</option>
                <option value="transportadora">Via transportadora</option>
            </select>
            @error('modalidadeEntrega') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <h2 class="text-lg font-semibold mb-3">Itens</h2>
    <table class="w-full text-sm border-collapse mb-6">
        <thead>
            <tr class="bg-gray-50 text-left">
                <th class="p-2 border">Descrição</th>
                <th class="p-2 border w-20">Qtd</th>
                <th class="p-2 border w-16">Un</th>
                <th class="p-2 border w-32">Valor Unit. (R$)</th>
                <th class="p-2 border w-32">Total (R$)</th>
                <th class="p-2 border">Destino</th>
            </tr>
        </thead>
        <tbody>
            @foreach($itens as $index => $item)
            <tr>
                <td class="p-2 border">{{ $item['descricao'] }}</td>
                <td class="p-2 border text-center">{{ $item['quantidade'] }}</td>
                <td class="p-2 border text-center">{{ $item['unidade_medida'] }}</td>
                <td class="p-2 border">
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        wire:model.blur="itens.{{ $index }}.valor_unitario"
                        wire:change="atualizarTotal({{ $index }})"
                        class="w-full border rounded px-2 py-1 text-sm"
                    />
                </td>
                <td class="p-2 border text-right font-mono">
                    R$ {{ number_format((float)$item['valor_total'], 2, ',', '.') }}
                </td>
                <td class="p-2 border">
                    <input
                        type="text"
                        wire:model.blur="itens.{{ $index }}.destino"
                        placeholder="Ex: Unidade Centro"
                        class="w-full border rounded px-2 py-1 text-sm"
                    />
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flex gap-3">
        <button wire:click="salvar" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Salvar Rascunho</button>
        <button wire:click="emitir" wire:confirm="Emitir o pedido? Esta ação é irreversível." class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Emitir Pedido</button>
        <button wire:click="$set('mostrarModalCancelar', true)" class="px-4 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">Cancelar PC</button>
        <a href="{{ route('compradora.pedidos.index') }}" class="px-4 py-2 border rounded hover:bg-gray-50">Voltar</a>
    </div>

    @if($mostrarModalCancelar)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Cancelar Pedido de Compra</h3>
            <textarea wire:model="motivoCancelamento" rows="3" placeholder="Motivo do cancelamento..." class="w-full border rounded p-2 text-sm mb-4"></textarea>
            @error('cancelamento') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('mostrarModalCancelar', false)" class="px-4 py-2 border rounded">Fechar</button>
                <button wire:click="cancelar" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Confirmar Cancelamento</button>
            </div>
        </div>
    </div>
    @endif
</div>
