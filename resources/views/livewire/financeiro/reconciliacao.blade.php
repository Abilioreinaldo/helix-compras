<div class="report-canvas">
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <a href="{{ route('pagamentos.index') }}" class="hover:text-slate-300">Pagamentos</a>
        <span>›</span>
        <span class="text-slate-400">Reconciliação</span>
    </nav>

    <x-page-header title="Reconciliação Bancária" icon="refresh" subtitle="Importe o extrato (CSV) e concilie com os pagamentos pela referência." />

    <x-report-card title="Importar extrato" icon="refresh">
        @error('formulario')<div class="mb-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-300">{{ $message }}</div>@enderror
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <x-filter-bar.field label="Banco" class="sm:w-64">
                <select wire:model="bancoId" class="input-dark w-full @error('bancoId') border-rose-500 @enderror">
                    <option value="">Selecione...</option>
                    @foreach ($bancos as $b)
                        <option value="{{ $b->id }}">{{ $b->nome }}</option>
                    @endforeach
                </select>
                @error('bancoId')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
            </x-filter-bar.field>
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium text-slate-300">Arquivo CSV (documento ; valor ; data ; descrição)</label>
                <input type="file" wire:model="arquivo" accept=".csv,.txt"
                    class="block w-full text-sm text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-slate-200 hover:file:bg-slate-700">
                <div wire:loading wire:target="arquivo" class="mt-1 text-xs text-slate-400">Enviando...</div>
                @error('arquivo')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
            </div>
            <button wire:click="processar" wire:loading.attr="disabled" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">Processar</button>
        </div>
    </x-report-card>

    @if ($reconciliacao)
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-metric-card label="Linhas processadas" :value="$reconciliacao->total_linhas" icon="document" accent="slate" />
            <x-metric-card label="Conciliado" :value="'R$ '.number_format((float) $reconciliacao->total_conciliado, 2, ',', '.')" icon="check-badge" accent="emerald" />
            <x-metric-card label="Total do extrato" :value="'R$ '.number_format((float) $reconciliacao->total_processado, 2, ',', '.')" icon="dollar" accent="sky" />
        </div>

        <x-report-card padding="p-0" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Documento</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Data</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($reconciliacao->itens as $item)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-slate-300">{{ $item->numero_documento }}</td>
                                <td class="px-4 py-3 text-right text-slate-300">R$ {{ number_format((float) $item->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $item->data_transacao?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $item->pagamento?->fornecedor?->nome_fantasia ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($item->status === 'conciliado')
                                        <span class="inline-flex rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-400">✓ Conciliado</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-400">⚠ Órfão</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
