<div class="report-canvas">
    <x-page-header
        title="Posição de Estoque"
        icon="cube"
        subtitle="Saldo atual por unidade e depósito. Saldos fundidos não são contabilizados."
    />

    <x-filter-bar>
        <x-filter-bar.field label="Unidade">
            <select wire:model.live="unidadeId" class="input-dark">
                <option value="">Todas as unidades</option>
                @foreach($unidades as $u)
                    <option value="{{ $u->id }}">{{ $u->nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Filtro">
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" wire:model.live="somenteAlerta" class="rounded border-zinc-600 bg-zinc-800 text-amber-500 focus:ring-amber-500/40">
                Somente em alerta
            </label>
        </x-filter-bar.field>
    </x-filter-bar>

    @if($posicao->isEmpty())
        <x-empty-state
            icon="cube"
            title="Nenhum saldo em estoque"
            message="Não há itens em estoque para os filtros selecionados. Ajuste a unidade ou remova o filtro de alerta."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <x-metric-card
                label="Valor Total em Estoque"
                :value="'R$ ' . number_format($valorTotalGeral, 2, ',', '.')"
                icon="dollar"
                accent="emerald"
            />
            <x-metric-card
                label="Itens no Estoque"
                :value="$posicao->count()"
                icon="cube"
                accent="emerald"
            />
            <x-metric-card
                label="Em Alerta"
                :value="$totalEmAlerta"
                icon="bolt"
                accent="emerald"
            />
        </div>

        <x-report-card title="Saldos por Item" icon="layers" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Depósito</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Saldo</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Validade</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Mínimo</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">CMP</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($posicao as $linha)
                            <tr class="{{ $linha->em_alerta ? 'bg-amber-500/5' : '' }}">
                                <td class="px-3 py-2.5 text-slate-300">{{ $linha->unidade_nome }}</td>
                                <td class="px-3 py-2.5 text-slate-400">{{ $linha->deposito }}</td>
                                <td class="px-3 py-2.5 text-slate-300 font-medium">
                                    {{ $linha->descricao_item }}
                                    @if($linha->em_alerta)
                                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-400">Abaixo do mínimo</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-300">{{ number_format($linha->saldo_atual, 3, ',', '.') }} {{ $linha->unidade_medida }}</td>
                                <td class="px-3 py-2.5">
                                    @include('partials.validade-lote', ['v' => $validades->get($linha->saldo_id)])
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-400">
                                    @if($linha->quantidade_minima !== null)
                                        {{ number_format($linha->quantidade_minima, 3, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-400">R$ {{ number_format($linha->custo_medio_ponderado, 2, ',', '.') }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-slate-100">R$ {{ number_format($linha->valor_total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-700 bg-zinc-950/40">
                            <td colspan="7" class="px-3 py-2.5 text-sm font-semibold text-slate-300">
                                Total
                                @if($totalEmAlerta > 0)
                                    <span class="ml-2 inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-400">{{ $totalEmAlerta }} em alerta</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right font-bold text-emerald-400">R$ {{ number_format($valorTotalGeral, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
