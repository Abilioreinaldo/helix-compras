<div class="report-canvas">
    <x-page-header title="Pedidos da Loja" icon="inbox" subtitle="Pedidos de compra recebidos das lojas (Store) aguardando promoção a requisição." />

    @error('promocao')
        <div class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300 mb-4">
            {{ $message }}
        </div>
    @enderror

    <div class="mb-4 flex items-center gap-2">
        @foreach ([
            \App\Models\PedidoLojaRecebido::STATUS_RECEBIDO => 'Recebidos',
            \App\Models\PedidoLojaRecebido::STATUS_PROMOVIDO => 'Promovidos',
            \App\Models\PedidoLojaRecebido::STATUS_DESCARTADO => 'Descartados',
            '' => 'Todos',
        ] as $valor => $rotulo)
            <button wire:click="$set('filtroStatus', '{{ $valor }}')"
                class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $filtroStatus === $valor ? 'bg-sky-500/15 text-sky-300' : 'text-slate-400 hover:bg-slate-800/60' }}">
                {{ $rotulo }}
            </button>
        @endforeach
    </div>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Pedido</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Loja</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor (loja)</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Itens / Estimado</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Recebido</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($pedidos as $pedido)
                        <tr class="transition-colors hover:bg-slate-800/40" wire:key="pedido-{{ $pedido->id }}">
                            <td class="px-4 py-3 font-mono text-slate-300">{{ $pedido->request_code }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $pedido->store_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $pedido->supplier_name ?? '—' }}
                                @if ($pedido->supplier_cnpj)
                                    <span class="block text-xs text-slate-500">CNPJ {{ $pedido->supplier_cnpj }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $pedido->line_count }} iten(s)
                                <span class="block text-xs text-slate-500">R$ {{ number_format($pedido->total_estimated_cents / 100, 2, ',', '.') }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $pedido->recebido_em?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($pedido->status === \App\Models\PedidoLojaRecebido::STATUS_RECEBIDO)
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-xs bg-sky-500/15 text-sky-400">Recebido</span>
                                @elseif ($pedido->status === \App\Models\PedidoLojaRecebido::STATUS_PROMOVIDO)
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-xs bg-emerald-500/15 text-emerald-400">Promovido</span>
                                    @if ($pedido->requisicao_id)
                                        <a href="{{ route('requisicoes.detalhe', $pedido->requisicao_id) }}" class="ml-1 text-xs text-sky-400 hover:underline">
                                            {{ $pedido->requisicao->codigo ?? 'ver requisição' }}
                                        </a>
                                    @endif
                                @else
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-xs bg-slate-500/15 text-slate-400">Descartado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($pedido->status === \App\Models\PedidoLojaRecebido::STATUS_RECEBIDO)
                                    <button wire:click="abrirPromocao({{ $pedido->id }})"
                                        class="rounded-lg bg-sky-500/15 px-3 py-1.5 text-xs font-medium text-sky-300 hover:bg-sky-500/25">
                                        Promover
                                    </button>
                                    <button wire:click="descartar({{ $pedido->id }})"
                                        wire:confirm="Descartar o pedido {{ $pedido->request_code }}? A loja não é notificada."
                                        class="ml-1 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-400 hover:bg-slate-800/60">
                                        Descartar
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($promovendo === $pedido->id)
                            <tr class="bg-slate-900/60">
                                <td colspan="7" class="px-4 py-4">
                                    <div class="flex flex-wrap items-end gap-4">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-400">Unidade de destino</label>
                                            <select wire:model.live="unidadeId" class="rounded-lg border-slate-700 bg-slate-950 text-sm text-slate-200">
                                                <option value="">—</option>
                                                @foreach ($unidades as $unidade)
                                                    <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                                                @endforeach
                                            </select>
                                            @error('unidadeId') <span class="block text-xs text-rose-400">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-400">Centro de custo</label>
                                            <select wire:model="centroCustoId" class="rounded-lg border-slate-700 bg-slate-950 text-sm text-slate-200" @disabled($centrosCusto->isEmpty())>
                                                <option value="">—</option>
                                                @foreach ($centrosCusto as $cc)
                                                    <option value="{{ $cc->id }}">{{ $cc->nome }}</option>
                                                @endforeach
                                            </select>
                                            @error('centroCustoId') <span class="block text-xs text-rose-400">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="promover" class="rounded-lg bg-sky-500 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-400">
                                                Criar requisição (rascunho)
                                            </button>
                                            <button wire:click="cancelarPromocao" class="rounded-lg px-4 py-2 text-xs font-medium text-slate-400 hover:bg-slate-800/60">
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">
                                        Os {{ $pedido->line_count }} iten(s) do pedido entram como avulsos na requisição — revise descrição, quantidades e valores antes de submeter.
                                    </p>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">Nenhum pedido da loja neste filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-report-card>

    <div class="mt-4">{{ $pedidos->links() }}</div>
</div>
