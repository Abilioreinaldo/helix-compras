<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Itens a Repor</h1>

    {{-- Filtros --}}
    <div class="flex gap-4 mb-6">
        <div class="flex-1">
            <input
                wire:model.live.debounce.400ms="busca"
                type="text"
                placeholder="Buscar por descrição do item..."
                class="w-full border-gray-300 rounded shadow-sm text-sm"
            />
        </div>
        <div>
            <select wire:model.live="filtroUnidadeId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">Todas as unidades</option>
                @foreach($unidades as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($itensPorUnidade->isEmpty())
        <div class="text-center py-12 text-gray-500">
            <p class="text-lg">Nenhum item abaixo do estoque mínimo encontrado.</p>
            <p class="text-sm mt-1">Todos os itens estão dentro dos limites definidos.</p>
        </div>
    @else
        @foreach($itensPorUnidade as $unidadeId => $itens)
            @php $unidadeNome = $itens->first()->unidade_nome; @endphp
            <div class="mb-8">
                <h2 class="text-base font-semibold text-gray-700 mb-3 border-b pb-1">{{ $unidadeNome }}</h2>
                <table class="min-w-full bg-white border rounded-lg shadow-sm text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Un. Medida</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mínimo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Atual</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">A repor</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($itens as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $item->item_descricao }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $item->unidade_medida ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->quantidade_minima, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right {{ (float) $item->saldo_atual <= 0 ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                    {{ number_format((float) $item->saldo_atual, 3, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-yellow-700">
                                    {{ number_format((float) $item->quantidade_sugerida, 3, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        wire:click="solicitarReposicao({{ $item->unidade_id }}, {{ $item->item_catalogo_id }}, {{ $item->quantidade_sugerida }})"
                                        class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700"
                                    >
                                        Solicitar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
</div>
