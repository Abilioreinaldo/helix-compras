<div class="report-canvas">
    {{-- Breadcrumb --}}
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <span class="text-slate-400">Cotações</span>
    </nav>

    <x-page-header
        title="Gestão de Cotações"
        icon="cotacao"
        subtitle="Acompanhe e gerencie todas as cotações em andamento."
    />

    <x-filter-bar>
        <x-filter-bar.field label="Situação">
            <select wire:model.live="filtroStatus" class="input-dark">
                <option value="">Todas</option>
                <option value="em_cotacao">Em cotação</option>
                <option value="cotacao_concluida">Concluídas</option>
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    @if($requisicoes->isEmpty())
        <x-empty-state
            icon="cotacao"
            title="Nenhuma cotação em andamento"
            message="As requisições enviadas para cotação aparecem aqui. Inicie uma a partir da triagem de Requisições."
        >
            <x-slot:action>
                <a href="{{ route('requisicoes.index') }}"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                    Ir para Requisições
                </a>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Requisição</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Itens</th>
                            <th class="px-4 py-2.5 text-center text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedores</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Situação</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Criada em</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($requisicoes as $req)
                            @php
                                $min = $req->is_emergencial ? 1 : ($req->faixaAlcada?->minimo_cotacoes ?? 3);
                                [$sitLabel, $sitCor] = match(true) {
                                    $req->cotacoes_vencedoras_count >= 1 => ['Vencedora definida', 'bg-emerald-500/15 text-emerald-400'],
                                    $req->status->value === 'cotacao_concluida' => ['Concluída', 'bg-emerald-500/15 text-emerald-400'],
                                    $req->cotacoes_confirmadas_count >= $min => ['Pronta para concluir', 'bg-sky-500/15 text-sky-400'],
                                    $req->cotacoes_count === 0 => ['Sem cotações', 'bg-slate-500/15 text-slate-300'],
                                    ($req->cotacoes_count - $req->cotacoes_confirmadas_count) > 0 => ['Aguardando respostas', 'bg-amber-500/15 text-amber-400'],
                                    default => ['Em andamento', 'bg-sky-500/15 text-sky-400'],
                                };
                            @endphp
                            <tr class="transition-colors hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('compradora.cotacoes', $req->id) }}" class="font-medium text-blue-400 hover:text-blue-300">
                                        {{ $req->codigo ?? 'REQ #'.$req->id }}
                                    </a>
                                    <span class="block text-xs text-slate-500">{{ $req->unidade?->nome ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ $req->itens_count }}</td>
                                <td class="px-4 py-3 text-center text-slate-300">
                                    {{ $req->cotacoes_confirmadas_count }}/{{ $min }}
                                    @if($req->cotacoes_count > $req->cotacoes_confirmadas_count)
                                        <span class="block text-xs text-slate-500">{{ $req->cotacoes_count }} contatado(s)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sitCor }}">{{ $sitLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ ($req->submetida_em ?? $req->created_at)?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('compradora.mapa-cotacao', ['requisicaoId' => $req->id]) }}" class="text-xs text-slate-400 hover:text-slate-200">
                                            Mapa
                                        </a>
                                        <a href="{{ route('compradora.cotacoes', $req->id) }}" class="text-xs font-medium text-blue-400 hover:text-blue-300">
                                            Ver detalhes
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>

        <div class="mt-4">
            {{ $requisicoes->links() }}
        </div>
    @endif
</div>
