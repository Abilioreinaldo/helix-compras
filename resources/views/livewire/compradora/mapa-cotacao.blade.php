<div class="report-canvas">
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <a href="{{ route('cotacoes.index') }}" class="hover:text-slate-300">Cotações</a>
        <span>›</span>
        <span class="text-slate-400">Mapa {{ $requisicao->codigo ?? 'REQ #'.$requisicao->id }}</span>
    </nav>

    <x-page-header
        title="Mapa de Cotação"
        icon="cotacao"
        :subtitle="($requisicao->codigo ?? 'REQ #'.$requisicao->id).' — '.($requisicao->unidade->nome ?? '—')"
    />

    @error('mapa')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    {{-- Resumo --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-metric-card label="Menor cotação" :value="$menor !== null ? 'R$ '.number_format((float) $menor, 2, ',', '.') : '—'" icon="dollar" accent="emerald" />
        <x-metric-card label="Maior cotação" :value="$maior !== null ? 'R$ '.number_format((float) $maior, 2, ',', '.') : '—'" icon="dollar" accent="slate" />
        <x-metric-card label="Economia (maior − menor)" :value="'R$ '.number_format((float) $economia, 2, ',', '.')" icon="trending-down" accent="emerald" hint="Potencial ao escolher a mais barata" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Itens da requisição (contexto) --}}
        <x-report-card title="Itens cotados" icon="document" class="lg:col-span-1">
            <ul class="space-y-2 text-sm">
                @forelse ($requisicao->itens as $item)
                    <li class="flex items-start justify-between gap-3">
                        <span class="text-slate-300">{{ $item->descricao }}</span>
                        <span class="shrink-0 text-slate-500">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }} {{ $item->unidade_medida }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">Sem itens.</li>
                @endforelse
            </ul>
            <p class="mt-3 border-t border-zinc-800 pt-3 text-xs text-slate-500">
                A cotação é por fornecedor (valor total da proposta), não por item.
            </p>
        </x-report-card>

        {{-- Comparativo de fornecedores --}}
        <x-report-card padding="p-0" class="lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Prazo</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Validade</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse ($cotacoes as $cotacao)
                            @php $melhor = $melhorId !== null && $cotacao->id === $melhorId; @endphp
                            <tr class="transition-colors hover:bg-zinc-800/40 {{ $cotacao->vencedora ? 'bg-emerald-500/10' : ($melhor ? 'bg-emerald-500/5' : '') }}">
                                <td class="px-4 py-3 text-slate-200">
                                    {{ $cotacao->fornecedor->nome_fantasia ?? '—' }}
                                    @if ($cotacao->vencedora)
                                        <span class="ml-1 inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-400">Vencedora</span>
                                    @elseif ($melhor)
                                        <span class="ml-1 inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-400">★ Melhor compra</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right {{ $melhor ? 'font-bold text-emerald-400' : 'font-medium text-slate-200' }}">
                                    @if ($cotacao->valor !== null)
                                        R$ {{ number_format((float) $cotacao->valor, 2, ',', '.') }}
                                    @elseif ($cotacao->valor_respondido !== null)
                                        <span class="font-normal text-slate-500">Sugerido: R$ {{ number_format((float) $cotacao->valor_respondido, 2, ',', '.') }}</span>
                                    @else
                                        <span class="font-normal italic text-slate-500">Aguardando</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-slate-300">
                                    {{ $cotacao->prazo_entrega_dias ? $cotacao->prazo_entrega_dias.' dias' : ($cotacao->prazo_respondido ? $cotacao->prazo_respondido.' dias*' : '—') }}
                                </td>
                                <td class="px-4 py-3 text-slate-400">
                                    {{ $cotacao->validade_proposta?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($emCotacao && ! $cotacao->vencedora && $cotacao->valor !== null)
                                        <button wire:click="marcarVencedora({{ $cotacao->id }})"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">
                                            Selecionar vencedor
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma cotação registrada para esta requisição.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-report-card>
    </div>
</div>
