<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Posição de Estoque</h1>
    <p class="text-sm text-gray-500 mb-6">
        Saldo atual por unidade e depósito. Saldos fundidos não são contabilizados.
    </p>

    <div class="flex gap-4 mb-6 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Unidade</label>
            <select wire:model.live="unidadeId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">Todas as unidades</option>
                @foreach($unidades as $u)
                    <option value="{{ $u->id }}">{{ $u->nome }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" wire:model.live="somenteAlerta" class="rounded border-gray-300">
            Somente em alerta
        </label>
    </div>

    @if($posicao->isEmpty())
        <p class="text-gray-500">Nenhum saldo em estoque para os filtros selecionados.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depósito</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mínimo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">CMP</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($posicao as $linha)
                        <tr class="hover:bg-gray-50 @if($linha->em_alerta) bg-red-50 @endif">
                            <td class="px-4 py-3 text-sm">{{ $linha->unidade_nome }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $linha->deposito }}</td>
                            <td class="px-4 py-3 text-sm font-medium">
                                {{ $linha->descricao_item }}
                                @if($linha->em_alerta)
                                    <span class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Abaixo do mínimo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format($linha->saldo_atual, 3, ',', '.') }} {{ $linha->unidade_medida }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">
                                @if($linha->quantidade_minima !== null)
                                    {{ number_format($linha->quantidade_minima, 3, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">R$ {{ number_format($linha->custo_medio_ponderado, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">R$ {{ number_format($linha->valor_total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-sm font-semibold">
                            Total ({{ $totalEmAlerta }} em alerta)
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-bold">R$ {{ number_format($valorTotalGeral, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
