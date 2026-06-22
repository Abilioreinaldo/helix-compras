<div class="report-canvas">
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <span class="text-slate-400">Aprovações</span>
    </nav>

    <x-page-header title="Fila de Aprovações" icon="check-badge" subtitle="Requisições aguardando sua aprovação." />

    <x-filter-bar>
        <x-filter-bar.field label="Unidade">
            <select wire:model.live="filtroUnidadeId" class="input-dark">
                <option value="">Todas</option>
                @foreach ($unidadesFiltro as $u)
                    <option value="{{ $u->id }}">{{ $u->nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Faixa de valor">
            <select wire:model.live="filtroFaixaId" class="input-dark">
                <option value="">Todas</option>
                @foreach ($faixas as $f)
                    <option value="{{ $f->id }}">{{ $f->nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Período">
            <select wire:model.live="filtroPeriodo" class="input-dark">
                <option value="">Todos</option>
                <option value="7">Últimos 7 dias</option>
                <option value="30">Últimos 30 dias</option>
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Solicitante</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Aprovação iniciada</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($requisicoes as $req)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 font-mono text-slate-300">
                                {{ $req->codigo ?? '—' }}
                                @if ($req->is_emergencial)
                                    <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs bg-rose-500/15 text-rose-400">Emergencial</span>
                                @endif
                                @if ($req->urgente)
                                    <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs bg-amber-500/15 text-amber-400">Urgente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $req->solicitante?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $req->unidade?->nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $req->aprovacao_iniciada_em?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('aprovacoes.painel', $req->id) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">Revisar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Nenhuma aprovação pendente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4 pb-4 border-t border-zinc-800 pt-3">
            {{ $requisicoes->links() }}
        </div>
    </x-report-card>
</div>
