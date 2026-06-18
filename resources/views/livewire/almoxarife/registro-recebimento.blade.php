<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Registrar Recebimento</h1>
            <p class="text-gray-500 text-sm">PC {{ $pedido->numero }} — {{ $pedido->fornecedor->razao_social }}</p>
        </div>
        <a href="{{ route('almoxarife.recebimentos.index') }}" class="px-4 py-2 border rounded hover:bg-gray-50">Voltar</a>
    </div>

    @error('recebimento')
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{{ $message }}</div>
    @enderror

    @error('quantidades')
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500">Unidade:</span>
                <span class="ml-2 font-medium">{{ $pedido->unidade->nome }}</span>
            </div>
            <div>
                <span class="text-gray-500">Emitido em:</span>
                <span class="ml-2">{{ $pedido->emitido_em?->format('d/m/Y') }}</span>
            </div>
            @if($pedido->prazo_entrega)
            <div>
                <span class="text-gray-500">Prazo de entrega:</span>
                <span class="ml-2">{{ $pedido->prazo_entrega->format('d/m/Y') }}</span>
            </div>
            @endif
            @if($pedido->modalidade_entrega)
            <div>
                <span class="text-gray-500">Modalidade:</span>
                <span class="ml-2">{{ $pedido->modalidade_entrega->label() }}</span>
            </div>
            @endif
        </div>
    </div>

    <form wire:submit="registrar">
        <div class="bg-white border rounded-lg overflow-hidden mb-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd. Pedida</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Já Recebido</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Receber Agora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($pedido->itens as $item)
                        @php
                            $recebido = (float) ($jaRecebidoPorItem[$item->id] ?? 0);
                            $saldo = (float) $item->quantidade - $recebido;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm">
                                {{ $item->descricao }}
                                @if($item->destino)
                                    <span class="text-xs text-gray-400 block">{{ $item->destino }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format($item->quantidade, 3, ',', '.') }} {{ $item->unidade_medida }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format($recebido, 3, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-right {{ $saldo <= 0 ? 'text-green-600 font-medium' : '' }}">
                                {{ $saldo <= 0 ? 'Completo' : number_format($saldo, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($saldo > 0)
                                    <input
                                        type="number"
                                        wire:model="quantidades.{{ $item->id }}"
                                        step="0.001"
                                        min="0"
                                        max="{{ $saldo }}"
                                        class="w-28 text-right border-gray-300 rounded shadow-sm text-sm"
                                        placeholder="0,000"
                                    />
                                    @if($controlaLote[$item->id] ?? false)
                                        <div class="mt-2 space-y-1">
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wide text-right">Lote / validade</div>
                                            <input
                                                type="text"
                                                wire:model="lotes.{{ $item->id }}.numero_lote"
                                                class="w-28 border-gray-300 rounded shadow-sm text-sm"
                                                placeholder="Nº do lote"
                                            />
                                            <input
                                                type="date"
                                                wire:model="lotes.{{ $item->id }}.validade"
                                                class="w-28 border-gray-300 rounded shadow-sm text-sm"
                                            />
                                            @error('lotes.'.$item->id.'.numero_lote')
                                                <span class="block text-xs text-red-600 text-right">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
            <textarea wire:model="observacoes" rows="3" class="w-full border-gray-300 rounded shadow-sm text-sm" placeholder="Opcional"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('almoxarife.recebimentos.index') }}" class="px-4 py-2 border rounded hover:bg-gray-50">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Confirmar Recebimento
            </button>
        </div>
    </form>

    @if($pedido->recebimentos->isNotEmpty())
    <div class="mt-8">
        <h2 class="text-lg font-semibold mb-3">Histórico de Recebimentos</h2>
        <div class="space-y-3">
            @foreach($pedido->recebimentos as $rec)
            <div class="bg-gray-50 border rounded p-3 text-sm">
                <div class="flex justify-between mb-1">
                    <span class="font-medium">{{ $rec->recebido_em->format('d/m/Y H:i') }}</span>
                    <span class="text-gray-500">{{ $rec->almoxarife?->name }}</span>
                </div>
                @foreach($rec->itens as $itemRec)
                    <div class="text-gray-600">{{ $itemRec->itemPedidoCompra?->descricao }}: {{ number_format($itemRec->quantidade_recebida, 3, ',', '.') }}</div>
                @endforeach
                @if($rec->observacoes)
                    <div class="text-gray-500 mt-1 italic">{{ $rec->observacoes }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
