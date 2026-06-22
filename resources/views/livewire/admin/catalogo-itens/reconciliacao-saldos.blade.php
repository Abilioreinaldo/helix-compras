<div class="report-canvas">
    <x-page-header title="Reconciliação de Saldos" icon="refresh" subtitle="Saldos de estoque ainda não vinculados a um item do catálogo. Confirme a sugestão ou busque manualmente o item correto." />

    @error('vinculo')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Depósito</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Sugestões</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($saldos as $saldo)
                        <tr class="align-top transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 text-slate-300">{{ $saldo->descricao_item }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $saldo->unidade->nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $saldo->deposito }}</td>
                            <td class="px-4 py-3">
                                @forelse ($sugestoes[$saldo->id] ?? [] as $sugestao)
                                    <div class="mb-1 flex items-center gap-2">
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium
                                            {{ $sugestao['confianca'] === 'alta' ? 'bg-emerald-500/15 text-emerald-400' : ($sugestao['confianca'] === 'media' ? 'bg-amber-500/15 text-amber-400' : 'bg-zinc-700/60 text-slate-400') }}">
                                            {{ ucfirst($sugestao['confianca']) }} ({{ number_format($sugestao['score'] * 100, 0) }}%)
                                        </span>
                                        <span class="text-slate-300">{{ $sugestao['item']->descricao }}</span>
                                        <button wire:click="vincular({{ $saldo->id }}, {{ $sugestao['item']->id }})" class="text-xs text-emerald-400 hover:text-emerald-300">Confirmar</button>
                                    </div>
                                @empty
                                    <span class="text-xs text-slate-500">Nenhuma sugestão encontrada.</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="abrirVinculoManual({{ $saldo->id }})" class="text-xs text-emerald-400 hover:text-emerald-300">Buscar manualmente</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Nenhum saldo pendente de reconciliação.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-800 px-4 pb-4 pt-3">
            {{ $saldos->links() }}
        </div>
    </x-report-card>

    {{-- Modal de busca manual --}}
    @if ($saldoSelecionadoId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-full max-w-lg rounded-xl border border-zinc-800 bg-zinc-900 p-6 text-slate-100 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-slate-100">Buscar Item de Catálogo</h2>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscaManual"
                    placeholder="Buscar por descrição ou código..."
                    class="mb-4 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-600"
                />

                <div class="max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($itensBuscaManual as $item)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                            <span class="text-sm text-slate-300">{{ $item->codigo ? $item->codigo.' — ' : '' }}{{ $item->descricao }}</span>
                            <button wire:click="vincular({{ $saldoSelecionadoId }}, {{ $item->id }})" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">Vincular</button>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Digite para buscar itens do catálogo.</p>
                    @endforelse
                </div>

                <div class="mt-4 flex justify-end">
                    <button wire:click="fecharVinculoManual" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
