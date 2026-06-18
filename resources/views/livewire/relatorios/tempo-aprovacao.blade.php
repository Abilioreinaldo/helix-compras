<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Tempo Médio de Aprovação</h1>
    <p class="text-sm text-gray-500 mb-6">
        Tempo do ciclo (da entrada na aprovação até a decisão) por faixa de alçada.
        Considera apenas requisições aprovadas com ciclo completo.
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
        <p class="text-gray-500">Nenhuma aprovação concluída para os filtros selecionados.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Faixa de Alçada</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nº Requisições</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tempo Médio</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mais Rápido</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mais Lento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($resultados as $linha)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium">{{ $linha->faixa_nome }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $linha->total_requisicoes }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">{{ number_format($linha->horas_media, 1, ',', '.') }} h</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">{{ number_format($linha->horas_min, 1, ',', '.') }} h</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">{{ number_format($linha->horas_max, 1, ',', '.') }} h</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr>
                        <td class="px-4 py-3 text-sm font-semibold">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-bold">{{ $totalRequisicoes }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
