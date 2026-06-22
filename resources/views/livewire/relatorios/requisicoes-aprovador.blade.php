<div class="report-canvas">
    <x-page-header
        title="Pendentes por Aprovador"
        icon="check-badge"
        subtitle="Snapshot atual — apenas aprovações do ciclo vigente de cada requisição."
    />

    @if($resultados->isEmpty())
        <x-empty-state
            icon="check-badge"
            title="Nenhuma aprovação pendente"
            message="Não há aprovações aguardando ação no momento. Todas as requisições do ciclo vigente já foram processadas."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card
                label="Total pendente"
                :value="$resultados->sum('total_pendentes')"
                icon="check-badge"
                accent="amber"
            />
        </div>

        <x-report-card title="Aprovadores com pendências" icon="users" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Aprovador</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Pendentes</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Mais Antiga</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($resultados as $linha)
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-slate-300">{{ $linha->aprovador_nome }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $linha->total_pendentes >= 5 ? 'bg-rose-500/15 text-rose-400' : 'bg-amber-500/15 text-amber-400' }}">
                                        {{ $linha->total_pendentes }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right {{ $linha->mais_antiga && \Carbon\Carbon::parse($linha->mais_antiga)->diffInDays() >= 7 ? 'text-rose-400' : ($linha->mais_antiga && \Carbon\Carbon::parse($linha->mais_antiga)->diffInDays() >= 3 ? 'text-amber-400' : 'text-slate-300') }}">
                                    {{ $linha->mais_antiga ? \Carbon\Carbon::parse($linha->mais_antiga)->format('d/m/Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-700 bg-zinc-950/40">
                            <td class="px-3 py-2.5 text-sm font-semibold text-slate-200">Total</td>
                            <td class="px-3 py-2.5 text-right text-sm font-bold text-slate-100">{{ $resultados->sum('total_pendentes') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
