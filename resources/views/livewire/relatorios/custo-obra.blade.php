<div class="report-canvas">
    <x-page-header title="Custo Acumulado por Obra" icon="building" subtitle="Curva de gastos mensal e acumulada por obra, frente à verba." />

    <x-filter-bar>
        <x-filter-bar.field label="Ano">
            <select wire:model.live="ano" class="input-dark">
                @foreach($anos as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Obra">
            <select wire:model.live="obraId" class="input-dark">
                <option value="">Todas as obras</option>
                @foreach($obras as $obra)
                    <option value="{{ $obra->id }}">{{ $obra->nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    @if($curvas->isEmpty())
        <x-empty-state
            icon="building"
            title="Nenhum gasto vinculado a obras"
            message="Não há lançamentos para os filtros selecionados. Ajuste o ano ou a obra para visualizar a curva de custo."
        />
    @else
        <div class="space-y-6">
            @foreach($curvas as $curva)
                <x-report-card padding="p-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 px-5 py-4">
                        <h2 class="text-base font-semibold text-white">{{ $curva['obra_nome'] }}</h2>
                        <div class="flex items-center gap-3 text-sm">
                            @if($curva['verba'] !== null)
                                <span class="text-slate-400">Verba: <span class="font-medium text-slate-200">R$ {{ number_format($curva['verba'], 2, ',', '.') }}</span></span>
                                @if($curva['percentual_verba'] !== null)
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $curva['percentual_verba'] >= 90 ? 'bg-rose-500/15 text-rose-400' : ($curva['percentual_verba'] >= 75 ? 'bg-amber-500/15 text-amber-400' : 'bg-emerald-500/15 text-emerald-400') }}">
                                        {{ $curva['percentual_verba'] }}% consumido
                                    </span>
                                @endif
                            @else
                                <span class="text-slate-500">Sem verba definida</span>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-800 bg-zinc-950/40">
                                    <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Métrica</th>
                                    @foreach($mesesAbrev as $abrev)
                                        <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">{{ $abrev }}</th>
                                    @endforeach
                                    <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800">
                                <tr>
                                    <td class="px-3 py-2.5 font-medium text-slate-300">Mensal</td>
                                    @foreach(range(1, 12) as $m)
                                        <td class="px-3 py-2.5 text-right {{ $curva['mensal'][$m] > 0 ? 'text-slate-300' : 'text-slate-600' }}">
                                            {{ $curva['mensal'][$m] > 0 ? number_format($curva['mensal'][$m], 0, ',', '.') : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2.5 text-right font-semibold text-slate-100">
                                        R$ {{ number_format($curva['total_ano'], 2, ',', '.') }}
                                    </td>
                                </tr>
                                <tr class="bg-zinc-950/40">
                                    <td class="px-3 py-2.5 font-medium text-slate-300">Acumulado</td>
                                    @foreach(range(1, 12) as $m)
                                        <td class="px-3 py-2.5 text-right {{ $curva['acumulado'][$m] > 0 ? 'text-emerald-400' : 'text-slate-600' }}">
                                            {{ $curva['acumulado'][$m] > 0 ? number_format($curva['acumulado'][$m], 0, ',', '.') : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-400">
                                        R$ {{ number_format($curva['total_ano'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-report-card>
            @endforeach
        </div>
    @endif
</div>
