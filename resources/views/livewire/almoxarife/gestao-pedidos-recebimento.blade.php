<div class="report-canvas">
    <x-page-header title="Recebimentos" icon="package" subtitle="Pedidos para Recebimento" />

    @if(session('sucesso'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('sucesso') }}</div>
    @endif

    @if($pedidos->isEmpty())
        <x-empty-state
            icon="package"
            title="Nenhum pedido aguardando recebimento"
            message="Nenhum pedido de compra emitido aguardando recebimento na sua unidade."
        />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Número</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Emitido em</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Recebimento</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($pedidos as $pedido)
                            @php $statusRec = $pedido->statusRecebimento(); @endphp
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3 font-mono text-slate-300">{{ $pedido->numero }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $pedido->fornecedor->razao_social }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $pedido->unidade->nome }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $pedido->emitido_em?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    @if($statusRec->value === 'total')
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-400">{{ $statusRec->label() }}</span>
                                    @elseif($statusRec->value === 'parcial')
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-400">{{ $statusRec->label() }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-slate-500/15 text-slate-300">{{ $statusRec->label() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($statusRec->value !== 'total')
                                        <a href="{{ route('almoxarife.recebimentos.registrar', $pedido->id) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500">Registrar</a>
                                    @else
                                        <span class="text-sm text-slate-400">Concluído</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 border-t border-zinc-800 px-4 pb-4 pt-3">
                {{ $pedidos->links() }}
            </div>
        </x-report-card>
    @endif
</div>
