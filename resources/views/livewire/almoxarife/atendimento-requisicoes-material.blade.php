<div class="report-canvas">
    <x-page-header title="Atendimento de Material" icon="hand" subtitle="Requisições de material abertas aguardando atendimento do almoxarife." />

    @if($erroAtendimento)
        <div class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300 mb-4">
            {{ $erroAtendimento }}
        </div>
    @endif

    @if($requisicoes->isEmpty())
        <x-empty-state
            icon="check-badge"
            title="Nenhuma requisição de material pendente"
            message="Nenhuma requisição de material pendente."
        />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">#</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Solicitante</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd Solicitada</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Saldo Disponível</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Justificativa</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($requisicoes as $req)
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3 font-mono text-slate-300">{{ $req->id }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $req->solicitante?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">
                                    {{ $req->saldoEstoque?->descricao_item ?? '—' }}
                                    @if ($saldoIdsVencidos->contains($req->saldo_estoque_id))
                                        <span class="ml-2 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-400" title="A saída debitará lote vencido">⚠️ Vencido</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format($req->quantidade_solicitada, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right {{ (float)$req->saldoEstoque?->quantidade < (float)$req->quantidade_solicitada ? 'font-medium text-rose-400' : 'text-slate-300' }}">
                                    {{ number_format($req->saldoEstoque?->quantidade ?? 0, 3, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ $req->justificativa }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <button
                                            wire:click="atender({{ $req->id }})"
                                            wire:confirm="Confirma o atendimento da requisição #{{ $req->id }}?"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 transition-colors"
                                        >
                                            Atender
                                        </button>
                                        <button
                                            wire:click="abrirRecusa({{ $req->id }})"
                                            class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-500 transition-colors"
                                        >
                                            Recusar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 px-4 pb-4 border-t border-zinc-800 pt-3">
                {{ $requisicoes->links() }}
            </div>
        </x-report-card>
    @endif

    {{-- Modal recusa --}}
    @if($recusandoId)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">Recusar Requisição #{{ $recusandoId }}</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Motivo da Recusa <span class="text-rose-400">*</span></label>
                        <textarea
                            wire:model="motivoRecusa"
                            rows="3"
                            class="input-dark w-full"
                            placeholder="Descreva o motivo da recusa..."
                        ></textarea>
                        @error('motivoRecusa') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <button wire:click="cancelarRecusa" class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700 transition-colors">
                            Cancelar
                        </button>
                        <button wire:click="confirmarRecusa" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                            Confirmar Recusa
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
