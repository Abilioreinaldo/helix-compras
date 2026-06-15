<div class="p-6">
    <h1 class="text-2xl font-bold mb-2">Compras Emergenciais</h1>
    <p class="text-sm text-gray-500 mb-6">
        Emergência recorrente indica falha de planejamento. Valor por cascata: PC emitido → cotação vencedora → estimativa.
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
        <p class="text-gray-500">Nenhuma compra emergencial encontrada para os filtros selecionados.</p>
    @else
        <div class="mb-4 flex gap-6">
            <div class="bg-orange-50 border border-orange-200 rounded-lg px-4 py-3">
                <p class="text-xs text-orange-600 font-medium uppercase">Total Emergenciais</p>
                <p class="text-2xl font-bold text-orange-700">{{ $totalEmergenciais }}</p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-lg px-4 py-3">
                <p class="text-xs text-orange-600 font-medium uppercase">Valor Total</p>
                <p class="text-2xl font-bold text-orange-700">R$ {{ number_format($totalValor, 2, ',', '.') }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Emergenciais</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($resultados as $linha)
                        <tr class="hover:bg-gray-50 {{ $linha->total_emergenciais >= 3 ? 'bg-orange-50' : '' }}">
                            <td class="px-4 py-3 text-sm">{{ $linha->unidade_nome }}</td>
                            <td class="px-4 py-3 text-sm">{{ $linha->solicitante_nome }}</td>
                            <td class="px-4 py-3 text-sm text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $linha->total_emergenciais >= 3 ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">
                                    {{ $linha->total_emergenciais }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium">R$ {{ number_format($linha->total_valor, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-sm font-semibold">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-bold">{{ $totalEmergenciais }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold">R$ {{ number_format($totalValor, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
