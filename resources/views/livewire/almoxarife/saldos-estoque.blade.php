<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Saldos de Estoque</h1>

    <div class="flex gap-4 mb-6">
        <div class="flex-1">
            <input
                wire:model.live.debounce.400ms="busca"
                type="text"
                placeholder="Buscar item..."
                class="w-full border-gray-300 rounded shadow-sm text-sm"
            />
        </div>
        <div>
            <select wire:model.live="deposito" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">Todos os depósitos</option>
                @foreach($depositos as $dep)
                    <option value="{{ $dep }}">{{ $dep }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($saldos->isEmpty())
        <p class="text-gray-500">Nenhum saldo encontrado para os filtros selecionados.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depósito</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantidade</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">CMP</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($saldos as $saldo)
                        <tr class="hover:bg-gray-50 {{ (float) $saldo->quantidade <= 0 ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3 text-sm">
                                {{ $saldo->descricao_item }}
                                @if($saldo->unidade_medida)
                                    <span class="text-xs text-gray-400 ml-1">{{ $saldo->unidade_medida }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $saldo->deposito }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $saldo->unidade->nome }}</td>
                            <td class="px-4 py-3 text-sm text-right {{ (float) $saldo->quantidade <= 0 ? 'text-red-600 font-medium' : '' }}">
                                {{ number_format($saldo->quantidade, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right">R$ {{ number_format($saldo->custo_medio_ponderado, 4, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">R$ {{ number_format($saldo->valor_total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $saldos->links() }}</div>
    @endif
</div>
