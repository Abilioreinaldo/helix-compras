<div class="report-canvas">
    <x-page-header title="Gastos por Centro de Custo" icon="chart-bar" subtitle="Total de pedidos emitidos agrupados por centro de custo no período." />

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
            icon="chart-bar"
            title="Nenhum gasto encontrado"
            message="Não há lançamentos para os filtros selecionados. Ajuste o ano ou o mês para visualizar os gastos por centro de custo."
        />
    @else
        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card
                label="Total do Período"
                :value="'R$ ' . number_format($totalGeral, 2, ',', '.')"
                icon="dollar"
                accent="emerald"
                hint="Soma de todos os pedidos emitidos"
            />
            <x-metric-card
                label="Centros de Custo"
                :value="$resultados->count()"
                icon="tag"
                accent="sky"
                hint="Com pelo menos um pedido no período"
            />
        </div>

        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Centro de Custo</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Nº Pedidos</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total Gasto</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">% do Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($resultados as $linha)
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-slate-300">{{ $linha->nome }}</td>
                                <td class="px-3 py-2.5 text-slate-400">{{ $linha->codigo }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-300">{{ $linha->total_pedidos }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-slate-100">R$ {{ number_format($linha->total_gasto, 2, ',', '.') }}</td>
                                <td class="px-3 py-2.5 text-right text-emerald-400">
                                    @if($totalGeral > 0)
                                        {{ number_format(($linha->total_gasto / $totalGeral) * 100, 1) }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-700 bg-slate-950/40">
                            <td colspan="3" class="px-3 py-2.5 font-semibold text-slate-300">Total</td>
                            <td class="px-3 py-2.5 text-right font-bold text-slate-100">R$ {{ number_format($totalGeral, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-slate-300">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
