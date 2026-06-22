<div class="report-canvas">
    <x-page-header title="Gastos por Fornecedor" icon="dollar" subtitle="Total gasto por fornecedor ou categoria nos pedidos emitidos, com participação percentual." />

    <x-filter-bar>
        <x-filter-bar.field label="Agrupar por">
            <select wire:model.live="agrupamento" class="input-dark">
                <option value="fornecedor">Fornecedor</option>
                <option value="categoria">Categoria</option>
            </select>
        </x-filter-bar.field>
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
            icon="dollar"
            title="Nenhum gasto encontrado"
            message="Não há pedidos emitidos para os filtros selecionados. Ajuste o agrupamento, o ano ou o mês para visualizar os gastos."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card label="Total Gasto" :value="'R$ ' . number_format($totalGeral, 2, ',', '.')" icon="dollar" accent="emerald" />
            <x-metric-card label="{{ $porCategoria ? 'Categorias' : 'Fornecedores' }}" :value="$resultados->count()" icon="{{ $porCategoria ? 'tag' : 'truck' }}" accent="emerald" />
            <x-metric-card label="Pedidos" :value="$resultados->sum('total_pedidos')" icon="clipboard" accent="emerald" />
            @if($porCategoria)
                <x-metric-card label="Fornecedores" :value="$resultados->sum('total_fornecedores')" icon="truck" accent="emerald" />
            @else
                <x-metric-card label="Maior Gasto" :value="'R$ ' . number_format($resultados->first()->total_gasto ?? 0, 2, ',', '.')" icon="chart-bar" accent="emerald" />
            @endif
        </div>

        <x-report-card title="{{ $porCategoria ? 'Gastos por Categoria' : 'Gastos por Fornecedor' }}" icon="{{ $porCategoria ? 'tag' : 'truck' }}" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                {{ $porCategoria ? 'Categoria' : 'Fornecedor' }}
                            </th>
                            @if($porCategoria)
                                <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Nº Fornecedores</th>
                            @else
                                <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Categoria</th>
                            @endif
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Nº Pedidos</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total Gasto</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">% do Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($resultados as $linha)
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-slate-300">{{ $linha->rotulo }}</td>
                                @if($porCategoria)
                                    <td class="px-3 py-2.5 text-right text-slate-300">{{ $linha->total_fornecedores }}</td>
                                @else
                                    <td class="px-3 py-2.5 text-slate-300">{{ $linha->categoria }}</td>
                                @endif
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
                        <tr class="border-t border-zinc-700 bg-zinc-950/40">
                            <td colspan="{{ $porCategoria ? 2 : 2 }}" class="px-3 py-2.5 font-semibold text-slate-300">Total</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-slate-300">{{ $resultados->sum('total_pedidos') }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-slate-100">R$ {{ number_format($totalGeral, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-emerald-400">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
