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

    @if ($cotacoes->isEmpty())
        <x-empty-state
            icon="cotacao"
            title="Nenhuma cotação ainda"
            message="Registre cotações dos fornecedores (ou solicite por e-mail) para comparar lado a lado."
        />
    @else
        @if ($cotacoes->count() < 2)
            <div class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
                Aguarde mais ofertas — apenas {{ $cotacoes->count() }} cotação registrada. O comparativo fica mais útil com 2+ fornecedores.
            </div>
        @endif

        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd</th>
                            @foreach ($cotacoes as $c)
                                <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">
                                    {{ $c->fornecedor->nome_fantasia ?? '—' }}
                                    @if ($c->vencedora)
                                        <span class="ml-1 inline-flex rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-medium text-emerald-400">Vencedora</span>
                                    @endif
                                </th>
                            @endforeach
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Melhor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ($itens as $item)
                            @php $melhorItem = $melhorPorItem[$item->id] ?? null; @endphp
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3 text-slate-300">{{ $item->descricao }}</td>
                                <td class="px-3 py-3 text-right text-slate-400">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }} {{ $item->unidade_medida }}</td>
                                @foreach ($cotacoes as $c)
                                    @php $v = $precoLinha[$c->id][$item->id] ?? null; $ehMelhor = $melhorItem && $melhorItem['cotacao_id'] === $c->id; @endphp
                                    <td class="px-4 py-3 text-right {{ $ehMelhor ? 'bg-emerald-500/5 font-semibold text-emerald-400' : 'text-slate-300' }}">
                                        {{ $v !== null ? 'R$ '.number_format($v, 2, ',', '.') : '—' }}
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right text-emerald-400">
                                    @if ($melhorItem)
                                        ★ R$ {{ number_format($melhorItem['valor'], 2, ',', '.') }}
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-800 bg-zinc-950/40">
                            <td class="px-4 py-3 font-semibold text-slate-200">Total</td>
                            <td></td>
                            @foreach ($cotacoes as $c)
                                <td class="px-4 py-3 text-right {{ $melhorTotalId === $c->id ? 'font-bold text-emerald-400' : 'font-medium text-slate-200' }}">
                                    @if ($c->valor !== null)
                                        {{ $melhorTotalId === $c->id ? '💚 ' : '' }}R$ {{ number_format((float) $c->valor, 2, ',', '.') }}
                                    @else
                                        <span class="italic text-slate-500">{{ $c->valor_respondido !== null ? 'Sugerido' : 'Aguardando' }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td></td>
                        </tr>
                        @if ($emCotacao)
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-xs text-slate-500">Selecionar vencedor</td>
                                @foreach ($cotacoes as $c)
                                    <td class="px-4 py-2 text-right">
                                        @if ($c->vencedora)
                                            <span class="text-xs font-medium text-emerald-400">✓ vencedora</span>
                                        @elseif ($c->valor !== null)
                                            <button wire:click="marcarVencedora({{ $c->id }})"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">
                                                Selecionar
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                                <td></td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </x-report-card>
    @endif
</div>
