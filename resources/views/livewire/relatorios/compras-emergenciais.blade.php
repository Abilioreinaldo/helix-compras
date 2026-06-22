<div class="report-canvas">
    <x-page-header
        title="Compras Emergenciais"
        icon="bolt"
        subtitle="Emergência recorrente indica falha de planejamento. Valor por cascata: PC emitido → cotação vencedora → estimativa."
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
            icon="bolt"
            title="Nenhuma compra emergencial encontrada"
            message="Não há compras emergenciais para os filtros selecionados. Ajuste o ano ou o mês para visualizar os dados."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card
                label="Total Emergenciais"
                :value="$totalEmergenciais"
                icon="bolt"
                accent="amber"
            />
            <x-metric-card
                label="Valor Total"
                :value="'R$ ' . number_format($totalValor, 2, ',', '.')"
                icon="dollar"
                accent="amber"
            />
        </div>

        <x-report-card title="Detalhamento por Solicitante" icon="users" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Solicitante</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Emergenciais</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($resultados as $linha)
                            <tr class="{{ $linha->total_emergenciais >= 3 ? 'bg-rose-500/5' : '' }}">
                                <td class="px-3 py-2.5 text-slate-300">{{ $linha->unidade_nome }}</td>
                                <td class="px-3 py-2.5 text-slate-300">{{ $linha->solicitante_nome }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $linha->total_emergenciais >= 3 ? 'bg-rose-500/15 text-rose-400' : 'bg-amber-500/15 text-amber-400' }}">
                                        {{ $linha->total_emergenciais }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-medium text-slate-300">R$ {{ number_format($linha->total_valor, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-700 bg-zinc-950/40">
                            <td colspan="2" class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Total</td>
                            <td class="px-3 py-2.5 text-right font-bold text-slate-100">{{ $totalEmergenciais }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-slate-100">R$ {{ number_format($totalValor, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
