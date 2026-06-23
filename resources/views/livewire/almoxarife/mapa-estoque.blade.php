<div class="report-canvas">
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <a href="{{ route('almoxarife.estoque.index') }}" class="hover:text-slate-300">Estoque</a>
        <span>›</span>
        <span class="text-slate-400">Mapa</span>
    </nav>

    <x-page-header title="Mapa de Estoque" icon="cube" subtitle="Posição por item, lote, validade e unidade — em tempo real." />

    {{-- Totais --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-metric-card label="Itens em estoque" :value="$totais['itens']" icon="cube" accent="slate" />
        <x-metric-card label="Abaixo do mínimo" :value="$totais['baixos']" icon="trending-down" accent="amber" />
        <x-metric-card label="Com lote vencido" :value="$totais['vencidos']" icon="bolt" accent="rose" />
        <x-metric-card label="Críticos (sem saldo)" :value="$totais['criticos']" icon="bolt" accent="rose" />
    </div>

    <x-filter-bar>
        <x-filter-bar.field label="Item">
            <input type="text" wire:model.live.debounce.400ms="filtroItem" placeholder="Descrição..." class="input-dark">
        </x-filter-bar.field>
        <x-filter-bar.field label="Unidade">
            <select wire:model.live="filtroUnidadeId" class="input-dark">
                <option value="">Todas</option>
                @foreach ($unidades as $u)
                    <option value="{{ $u['id'] }}">{{ $u['nome'] }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Lote">
            <input type="text" wire:model.live.debounce.400ms="filtroLote" placeholder="Nº do lote..." class="input-dark">
        </x-filter-bar.field>
        <x-filter-bar.field label="&nbsp;">
            <label class="flex items-center gap-2 py-2 text-sm text-slate-300">
                <input type="checkbox" wire:model.live="apenasVencidos" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-rose-500 focus:ring-rose-500/40">
                Apenas vencidos
            </label>
        </x-filter-bar.field>
    </x-filter-bar>

    @if ($linhas->isEmpty())
        <x-empty-state icon="cube" title="Nada em estoque" message="Nenhum saldo encontrado para os filtros atuais." />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Lote</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Validade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ($linhas as $linha)
                            @php
                                [$rotulo, $emoji, $cor] = match ($linha->status) {
                                    'critico' => ['Crítico', '🔴', 'bg-rose-500/15 text-rose-400'],
                                    'vencido' => ['Vencido', '⚠️', 'bg-orange-500/15 text-orange-400'],
                                    'baixo' => ['Baixo', '📉', 'bg-amber-500/15 text-amber-400'],
                                    default => ['OK', '✅', 'bg-emerald-500/15 text-emerald-400'],
                                };
                                $venceu = $linha->status === 'vencido';
                            @endphp
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3">
                                    <span class="text-slate-200">{{ $linha->descricao_item }}</span>
                                    @if ($linha->unidade_medida)<span class="ml-1 text-xs text-slate-500">({{ $linha->unidade_medida }})</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-200">{{ number_format((float) $linha->saldo_atual, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-slate-400">
                                    @if ($linha->lotes->isEmpty())
                                        —
                                    @else
                                        {{ $linha->lotes->first()->numero_lote ?? 's/ nº' }}
                                        @if ($linha->lotes->count() > 1)<span class="text-xs text-slate-500">+{{ $linha->lotes->count() - 1 }}</span>@endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 {{ $venceu ? 'font-medium text-orange-400' : 'text-slate-400' }}">
                                    {{ $linha->proxima_validade?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-300">
                                    {{ $linha->unidade_nome }}
                                    @if ($linha->deposito)<span class="block text-xs text-slate-500">{{ $linha->deposito }}</span>@endif
                                </td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $cor }}">{{ $emoji }} {{ $rotulo }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('almoxarife.estoque.index') }}" class="text-xs font-medium text-emerald-400 hover:text-emerald-300">Estoque</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-800 px-4 py-3">{{ $linhas->links() }}</div>
        </x-report-card>
    @endif
</div>
