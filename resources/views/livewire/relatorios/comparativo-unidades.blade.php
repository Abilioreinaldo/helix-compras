<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Comparativo entre Unidades</h1>
    <p class="text-sm text-gray-500 mb-6">
        Gasto comprometido (pedidos emitidos) atribuído à unidade da requisição.
    </p>

    <div class="flex gap-4 mb-6">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano</label>
            <select wire:model.live="ano" class="border-gray-300 rounded shadow-sm text-sm">
                @foreach($anos as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Mês</label>
            <select wire:model.live="mes" class="border-gray-300 rounded shadow-sm text-sm">
                @foreach($meses as $v => $label)
                    <option value="{{ $v }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($resultados->isEmpty())
        <p class="text-gray-500">Nenhum gasto registrado para os filtros selecionados.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nº Requisições</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nº Pedidos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Gasto</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gasto Médio/Req</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">% do Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($resultados as $linha)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium">{{ $linha->unidade_nome }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $linha->total_requisicoes }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $linha->total_pedidos }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">R$ {{ number_format($linha->total_gasto, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">
                                @if($linha->total_requisicoes > 0)
                                    R$ {{ number_format($linha->total_gasto / $linha->total_requisicoes, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">
                                @if($totalGeral > 0)
                                    {{ number_format(($linha->total_gasto / $totalGeral) * 100, 1) }}%
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-semibold">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-bold">R$ {{ number_format($totalGeral, 2, ',', '.') }}</td>
                        <td></td>
                        <td class="px-4 py-3 text-sm text-right font-semibold">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
