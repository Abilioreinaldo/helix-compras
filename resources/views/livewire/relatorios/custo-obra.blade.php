<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Custo Acumulado por Obra</h1>

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
            <label class="block text-xs font-medium text-gray-500 mb-1">Obra</label>
            <select wire:model.live="obraId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">Todas as obras</option>
                @foreach($obras as $obra)
                    <option value="{{ $obra->id }}">{{ $obra->nome }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($curvas->isEmpty())
        <p class="text-gray-500">Nenhum gasto vinculado a obras para os filtros selecionados.</p>
    @else
        @foreach($curvas as $curva)
            <div class="mb-8">
                <div class="flex items-baseline gap-4 mb-3">
                    <h2 class="text-lg font-semibold">{{ $curva['obra_nome'] }}</h2>
                    @if($curva['verba'] !== null)
                        <span class="text-sm text-gray-500">
                            Verba: R$ {{ number_format($curva['verba'], 2, ',', '.') }}
                        </span>
                        @if($curva['percentual_verba'] !== null)
                            <span class="text-sm font-medium {{ $curva['percentual_verba'] >= 90 ? 'text-red-600' : ($curva['percentual_verba'] >= 75 ? 'text-yellow-600' : 'text-green-600') }}">
                                {{ $curva['percentual_verba'] }}% consumido
                            </span>
                        @endif
                    @else
                        <span class="text-sm text-gray-400">Sem verba definida</span>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border rounded-lg shadow-sm text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Métrica</th>
                                @foreach($mesesAbrev as $abrev)
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $abrev }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-700">Mensal</td>
                                @foreach(range(1, 12) as $m)
                                    <td class="px-3 py-2 text-right {{ $curva['mensal'][$m] > 0 ? '' : 'text-gray-300' }}">
                                        {{ $curva['mensal'][$m] > 0 ? number_format($curva['mensal'][$m], 0, ',', '.') : '—' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-semibold">
                                    R$ {{ number_format($curva['total_ano'], 2, ',', '.') }}
                                </td>
                            </tr>
                            <tr class="bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-700">Acumulado</td>
                                @foreach(range(1, 12) as $m)
                                    <td class="px-3 py-2 text-right {{ $curva['acumulado'][$m] > 0 ? 'text-blue-700' : 'text-gray-300' }}">
                                        {{ $curva['acumulado'][$m] > 0 ? number_format($curva['acumulado'][$m], 0, ',', '.') : '—' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-bold text-blue-700">
                                    R$ {{ number_format($curva['total_ano'], 2, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
