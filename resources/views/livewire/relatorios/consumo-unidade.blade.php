<div class="report-canvas">
    <x-page-header title="Consumo por Unidade" icon="chart-pie" subtitle="Valor das saídas de estoque (material consumido) por unidade no período." />

    <x-filter-bar>
        <x-filter-bar.field label="Ano">
            <select wire:model.live="ano" class="input-dark">
                @foreach($anos as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Mês">
            <select wire:model.live="mes" class="input-dark">
                @foreach($meses as $v => $label)
                    <option value="{{ $v }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    @if($resultados->isEmpty())
        <x-empty-state
            icon="chart-pie"
            title="Nenhum consumo registrado"
            message="Não há saídas de estoque para os filtros selecionados. Ajuste o ano ou o mês para visualizar o consumo por unidade."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card label="Total Consumido" :value="'R$ ' . number_format($totalGeral, 2, ',', '.')" icon="dollar" accent="emerald" />
            <x-metric-card label="Unidades com Consumo" :value="$resultados->count()" icon="building" accent="emerald" />
            <x-metric-card label="Total de Saídas" :value="$resultados->sum('total_saidas')" icon="trending-down" accent="emerald" />
            <x-metric-card label="Média por Unidade" :value="'R$ ' . number_format($resultados->count() > 0 ? $totalGeral / $resultados->count() : 0, 2, ',', '.')" icon="chart-bar" accent="emerald" />
        </div>

        <x-report-card title="Consumo por Unidade" icon="chart-pie" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Nº Saídas</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor Consumido</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">% do Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($resultados as $linha)
                            <tr>
                                <td class="px-3 py-2.5 text-slate-300">{{ $linha->unidade_nome }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-300">{{ $linha->total_saidas }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-slate-100">R$ {{ number_format($linha->total_consumido, 2, ',', '.') }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-300">
                                    @if($totalGeral > 0)
                                        {{ number_format(($linha->total_consumido / $totalGeral) * 100, 1) }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-800 bg-zinc-950/40">
                            <td colspan="2" class="px-3 py-2.5 font-semibold text-slate-300">Total</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-slate-100">R$ {{ number_format($totalGeral, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-slate-300">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
