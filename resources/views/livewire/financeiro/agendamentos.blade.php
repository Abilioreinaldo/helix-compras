<div class="report-canvas">
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <a href="{{ route('pagamentos.index') }}" class="hover:text-slate-300">Pagamentos</a>
        <span>›</span>
        <span class="text-slate-400">Agendamentos</span>
    </nav>

    <x-page-header title="Agendamentos" icon="clock" subtitle="Pagamentos com vencimento nos próximos 30 dias.">
        <x-slot:actions>
            <button wire:click="exportar" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">Exportar para o banco (CSV)</button>
        </x-slot:actions>
    </x-page-header>

    @if ($pagamentos->isEmpty())
        <x-empty-state icon="clock" title="Nada nos próximos 30 dias" message="Não há pagamentos em aberto vencendo no período." />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Vencimento</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">A pagar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($pagamentos as $pag)
                            @php $venceu = $pag->ehVencido(); @endphp
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-3 {{ $venceu ? 'font-medium text-rose-400' : 'text-slate-300' }}">
                                    {{ $pag->data_vencimento?->format('d/m/Y') }}
                                    <span class="block text-xs text-slate-500">{{ $pag->diasAteVencimento() >= 0 ? 'em '.$pag->diasAteVencimento().' dia(s)' : 'vencido' }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-200">{{ $pag->fornecedor?->nome_fantasia ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $pag->status->value === 'agendado' ? 'bg-sky-500/15 text-sky-400' : 'bg-slate-500/15 text-slate-300' }}">{{ $pag->status->rotulo() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-200">R$ {{ number_format((float) ($pag->valor_total - $pag->valor_pago), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
