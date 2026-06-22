<div class="report-canvas">
    <x-page-header
        title="Tempo de Aprovação"
        icon="clock"
        subtitle="Tempo do ciclo de aprovação (da entrada até a decisão) por faixa de alçada. Considera apenas requisições aprovadas com ciclo completo."
    />

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
            icon="clock"
            title="Nenhuma aprovação concluída"
            message="Não há ciclos de aprovação completos para os filtros selecionados. Ajuste o ano ou o mês para visualizar os tempos."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($resultados as $linha)
                <x-metric-card
                    label="{{ $linha->faixa_nome }}"
                    :value="number_format($linha->horas_media, 1, ',', '.') . ' h'"
                    icon="clock"
                    accent="emerald"
                />
            @endforeach
        </div>

        <x-report-card title="Detalhamento por Faixa de Alçada" icon="layers" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Faixa de Alçada</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Nº Requisições</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Tempo Médio</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Mais Rápido</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Mais Lento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($resultados as $linha)
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-slate-300">{{ $linha->faixa_nome }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-300">{{ $linha->total_requisicoes }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-emerald-400">{{ number_format($linha->horas_media, 1, ',', '.') }} h</td>
                                <td class="px-3 py-2.5 text-right text-slate-400">{{ number_format($linha->horas_min, 1, ',', '.') }} h</td>
                                <td class="px-3 py-2.5 text-right text-slate-400">{{ number_format($linha->horas_max, 1, ',', '.') }} h</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-700 bg-zinc-950/40">
                            <td class="px-3 py-2.5 font-semibold text-slate-200">Total</td>
                            <td class="px-3 py-2.5 text-right font-bold text-slate-100">{{ $totalRequisicoes }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
