<div class="report-canvas">
    <x-page-header title="Itens a Repor" icon="trending-down" subtitle="Itens abaixo do estoque mínimo, por unidade, com sugestão de reposição." />

    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="min-w-[220px] flex-1">
            <input
                wire:model.live.debounce.400ms="busca"
                type="text"
                placeholder="Buscar por descrição do item..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Unidade">
            <select wire:model.live="filtroUnidadeId" class="input-dark">
                <option value="">Todas as unidades</option>
                @foreach($unidades as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    @if($itensPorUnidade->isEmpty())
        <x-empty-state
            icon="check-badge"
            title="Nenhum item abaixo do estoque mínimo encontrado"
            message="Todos os itens estão dentro dos limites definidos. Nada a repor no momento."
        />
    @else
        <div class="space-y-6">
            @foreach($itensPorUnidade as $unidadeId => $itens)
                <x-report-card :title="$itens->first()->unidade_nome" icon="building" padding="p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-800 bg-zinc-950/40">
                                    <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Un. Medida</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Mínimo</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Saldo Atual</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">A repor</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-medium uppercase tracking-wide text-slate-500">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800">
                                @foreach($itens as $item)
                                    <tr class="transition-colors hover:bg-zinc-800/40">
                                        <td class="px-4 py-3 text-slate-200">{{ $item->item_descricao }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ $item->unidade_medida ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300">{{ number_format((float) $item->quantidade_minima, 3, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right {{ (float) $item->saldo_atual <= 0 ? 'font-medium text-rose-400' : 'text-slate-300' }}">
                                            {{ number_format((float) $item->saldo_atual, 3, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-amber-400">
                                            {{ number_format((float) $item->quantidade_sugerida, 3, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                wire:click="solicitarReposicao({{ $item->unidade_id }}, {{ $item->item_catalogo_id }}, {{ $item->quantidade_sugerida }})"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-emerald-500"
                                            >
                                                Solicitar
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-report-card>
            @endforeach
        </div>
    @endif
</div>
