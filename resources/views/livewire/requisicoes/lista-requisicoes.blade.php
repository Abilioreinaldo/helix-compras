<div class="report-canvas">
    <x-page-header title="Minhas Requisições" icon="document" subtitle="Acompanhe e filtre suas requisições de compra." />

    <x-filter-bar>
        <x-filter-bar.field label="Status">
            <select wire:model.live="filtroStatus" class="input-dark">
                <option value="">Todos os status</option>
                @foreach ($statusDisponiveis as $s)
                    <option value="{{ $s->value }}">{{ ucwords(str_replace('_', ' ', $s->value)) }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Filtros adicionais">
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" wire:model.live="filtroUrgente" class="rounded">
                Somente urgentes
            </label>
        </x-filter-bar.field>
        <x-filter-bar.field label="&nbsp;">
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" wire:model.live="filtroAtrasada" class="rounded">
                Somente atrasadas
            </label>
        </x-filter-bar.field>
        <x-filter-bar.field label="&nbsp;">
            <a href="{{ route('requisicoes.criar') }}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500">
                Nova Requisição
            </a>
        </x-filter-bar.field>
    </x-filter-bar>

    @if ($requisicoes->isEmpty())
        <x-empty-state
            icon="document"
            title="Nenhuma requisição encontrada."
            message="Nenhuma requisição corresponde aos filtros selecionados. Ajuste os filtros ou crie uma nova requisição."
        />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Itens</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Solicitante</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Criada em</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($requisicoes as $req)
                            <tr class="transition-colors hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono text-slate-300">
                                    {{ $req->codigo ?? '(rascunho)' }}
                                    @if ($req->atrasada)
                                        <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-rose-500/15 text-rose-400">Atrasada</span>
                                    @endif
                                    @if ($req->urgente)
                                        <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-amber-500/15 text-amber-400">Urgente</span>
                                    @endif
                                    @if ($req->is_emergencial)
                                        <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-rose-500/15 text-rose-400">Emergencial</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $req->itens->count() }} {{ Str::plural('item', $req->itens->count()) }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $req->unidade->nome ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $req->solicitante->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusVal = $req->status->value;
                                        $badgeClass = match($statusVal) {
                                            'pendente'    => 'bg-amber-500/15 text-amber-400',
                                            'aprovada'    => 'bg-emerald-500/15 text-emerald-400',
                                            'em_compra'   => 'bg-sky-500/15 text-sky-400',
                                            'concluida'   => 'bg-violet-500/15 text-violet-400',
                                            'cancelada'   => 'bg-rose-500/15 text-rose-400',
                                            default       => 'bg-slate-500/15 text-slate-300',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                        {{ ucwords(str_replace('_', ' ', $statusVal)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('requisicoes.detalhe', $req->id) }}" class="text-blue-400 hover:text-blue-300 text-sm">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-800">
                {{ $requisicoes->links() }}
            </div>
        </x-report-card>
    @endif
</div>
