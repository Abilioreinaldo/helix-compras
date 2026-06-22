<div class="report-canvas">
    <x-page-header title="Inventário" icon="clipboard" subtitle="Contagem física e ajuste de estoque por sessão de inventário." />

    @if($erro)
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            {{ $erro }}
        </div>
    @endif

    @if(!$sessaoAtiva && !$mostrarFormAbrir)
        <div class="mb-6 flex justify-end">
            <button wire:click="abrirFormAbrir" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Iniciar Inventário
            </button>
        </div>
    @endif

    @if($mostrarFormAbrir)
        <x-report-card class="mb-6">
            <h2 class="mb-4 text-base font-semibold text-slate-100">Abrir Nova Sessão de Inventário</h2>
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">
                        Depósito (deixe em branco para inventário da unidade inteira)
                    </label>
                    <input
                        wire:model="depositoAbertura"
                        type="text"
                        class="input-dark w-full"
                        placeholder="Ex: Depósito Central (opcional)"
                    />
                </div>
                <div class="flex gap-3">
                    <button wire:click="abrirSessao" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                        Abrir Sessão
                    </button>
                    <button wire:click="fecharFormAbrir" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>
        </x-report-card>
    @endif

    @if($sessaoAtiva)
        <x-report-card class="mb-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-100">
                    Sessão #{{ $sessaoAtiva->id }}
                    @if($sessaoAtiva->deposito)
                        — {{ $sessaoAtiva->deposito }}
                    @else
                        — Unidade Inteira
                    @endif
                </h2>
                <span class="rounded-full bg-amber-500/15 px-2 py-1 text-xs font-medium text-amber-400">
                    {{ $sessaoAtiva->status->label() }}
                </span>
            </div>

            @if($mostrarModalAplicar)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                    <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 text-slate-100 shadow-xl">
                        <h3 class="mb-4 text-lg font-bold text-slate-100">Aplicar Inventário</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Justificativa</label>
                                <textarea
                                    wire:model="justificativaAplicar"
                                    rows="2"
                                    class="input-dark w-full"
                                    placeholder="Descreva o motivo do inventário..."
                                ></textarea>
                            </div>
                            <div class="flex justify-end gap-3 pt-1">
                                <button wire:click="fecharModalAplicar" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                                    Cancelar
                                </button>
                                <button wire:click="aplicar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                                    Confirmar Aplicação
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <x-report-card padding="p-0" class="mb-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-800 bg-zinc-950/40">
                                <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd Sistema</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd Contada</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Divergência</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            @foreach($sessaoAtiva->itens as $item)
                                <tr class="transition-colors hover:bg-zinc-800/40">
                                    <td class="px-4 py-3 text-slate-300">{{ $item->saldoEstoque?->descricao_item ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-300">{{ number_format($item->quantidade_sistema, 3, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            wire:model="quantidadesContadas.{{ $item->id }}"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            class="input-dark w-28 text-right"
                                            placeholder="0.000"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if(isset($quantidadesContadas[$item->id]) && is_numeric($quantidadesContadas[$item->id]))
                                            @php $div = (float)$quantidadesContadas[$item->id] - (float)$item->quantidade_sistema; @endphp
                                            <span class="{{ $div > 0 ? 'text-emerald-400' : ($div < 0 ? 'text-rose-400' : 'text-slate-400') }}">
                                                {{ ($div >= 0 ? '+' : '') . number_format($div, 3, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-report-card>

            <div class="flex gap-3">
                <button wire:click="abrirModalAplicar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                    Aplicar Inventário
                </button>
                <button
                    wire:click="cancelar"
                    wire:confirm="Confirma o cancelamento desta sessão de inventário?"
                    class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors"
                >
                    Cancelar Sessão
                </button>
            </div>
        </x-report-card>
    @endif

    @if($historico->isNotEmpty())
        <x-report-card padding="p-0">
            <div class="px-4 py-3 border-b border-zinc-800">
                <h2 class="text-base font-semibold text-slate-100">Histórico Recente</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">#</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Depósito</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($historico as $s)
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3 text-slate-400">{{ $s->id }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $s->unidade?->nome ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $s->deposito ?? 'Unidade Inteira' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $cor = match($s->status) {
                                            \App\Enums\StatusInventario::Concluido => 'bg-emerald-500/15 text-emerald-400',
                                            \App\Enums\StatusInventario::Cancelado => 'bg-rose-500/15 text-rose-400',
                                            default => 'bg-amber-500/15 text-amber-400',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $cor }}">{{ $s->status->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
