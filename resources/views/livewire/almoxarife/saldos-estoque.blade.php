<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Saldos de Estoque</h1>

    {{-- Painel: Itens a repor --}}
    @if($itensARepor->isNotEmpty())
        <div class="mb-6 bg-yellow-50 border border-yellow-300 rounded-lg p-4">
            <h2 class="text-base font-semibold text-yellow-800 mb-3">Itens a repor</h2>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-yellow-700 uppercase">
                        <th class="pb-1 pr-4">Item</th>
                        <th class="pb-1 pr-4">Un. Medida</th>
                        <th class="pb-1 pr-4 text-right">Mínimo</th>
                        <th class="pb-1 pr-4 text-right">Saldo Atual</th>
                        <th class="pb-1 text-right">A repor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itensARepor as $repor)
                        <tr class="border-t border-yellow-200">
                            <td class="py-1 pr-4 text-gray-800">{{ $repor->item_descricao }}</td>
                            <td class="py-1 pr-4 text-gray-500">{{ $repor->unidade_medida ?? '—' }}</td>
                            <td class="py-1 pr-4 text-right">{{ number_format((float) $repor->quantidade_minima, 3, ',', '.') }}</td>
                            <td class="py-1 pr-4 text-right {{ (float) $repor->saldo_atual <= 0 ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                {{ number_format((float) $repor->saldo_atual, 3, ',', '.') }}
                            </td>
                            <td class="py-1 text-right font-semibold text-yellow-800">
                                {{ number_format((float) $repor->quantidade_sugerida, 3, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Filtros --}}
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
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($saldos as $saldo)
                        @php
                            $emAlerta = $saldo->item_catalogo_id && in_array($saldo->item_catalogo_id, $idsEmAlerta);
                            $linhaClass = (float) $saldo->quantidade <= 0 ? 'bg-red-50' : ($emAlerta ? 'bg-yellow-50' : '');
                        @endphp
                        <tr class="hover:bg-gray-50 {{ $linhaClass }}">
                            <td class="px-4 py-3 text-sm">
                                {{ $saldo->descricao_item }}
                                @if($saldo->unidade_medida)
                                    <span class="text-xs text-gray-400 ml-1">{{ $saldo->unidade_medida }}</span>
                                @endif
                                @if($emAlerta)
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Abaixo do mínimo
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $saldo->deposito }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $saldo->unidade->nome }}</td>
                            <td class="px-4 py-3 text-sm text-right {{ (float) $saldo->quantidade <= 0 ? 'text-red-600 font-medium' : '' }}">
                                {{ number_format($saldo->quantidade, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right">R$ {{ number_format($saldo->custo_medio_ponderado, 4, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">R$ {{ number_format($saldo->valor_total, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($saldo->item_catalogo_id)
                                    <button
                                        wire:click="abrirModalMinimo({{ $saldo->id }})"
                                        class="text-xs text-indigo-600 hover:underline"
                                    >
                                        Definir mínimo
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $saldos->links() }}</div>
    @endif

    {{-- Modal: Definir Estoque Mínimo --}}
    @if($mostrarModalMinimo)
        <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h2 class="text-lg font-semibold mb-1">Definir Estoque Mínimo</h2>
                <p class="text-sm text-gray-500 mb-4">{{ $minimoDescricaoItem }}</p>

                @if($errors->has('minimoQuantidade'))
                    <div class="mb-3 text-sm text-red-600">{{ $errors->first('minimoQuantidade') }}</div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Quantidade mínima <span class="text-gray-400 font-normal">(0 = remover)</span>
                    </label>
                    <input
                        wire:model="minimoQuantidade"
                        type="number"
                        min="0"
                        step="0.001"
                        class="w-full border-gray-300 rounded shadow-sm text-sm"
                        placeholder="Ex.: 10"
                        autofocus
                    />
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        wire:click="fecharModalMinimo"
                        class="px-4 py-2 text-sm bg-gray-100 rounded hover:bg-gray-200"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="salvarMinimo"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    >
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
