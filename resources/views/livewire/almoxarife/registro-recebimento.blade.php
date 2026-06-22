<div class="report-canvas">
    <x-page-header
        title="Registrar Recebimento"
        icon="package"
        subtitle="PC {{ $pedido->numero }} — {{ $pedido->fornecedor->razao_social }}"
    />

    @if(session('sucesso'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('sucesso') }}
        </div>
    @endif

    @error('recebimento')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    @error('quantidades')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    {{-- Painel de informações do pedido --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-slate-400">Unidade:</span>
                <span class="ml-2 font-medium text-slate-200">{{ $pedido->unidade->nome }}</span>
            </div>
            <div>
                <span class="text-slate-400">Emitido em:</span>
                <span class="ml-2 text-slate-300">{{ $pedido->emitido_em?->format('d/m/Y') }}</span>
            </div>
            @if($pedido->prazo_entrega)
            <div>
                <span class="text-slate-400">Prazo de entrega:</span>
                <span class="ml-2 text-slate-300">{{ $pedido->prazo_entrega->format('d/m/Y') }}</span>
            </div>
            @endif
            @if($pedido->modalidade_entrega)
            <div>
                <span class="text-slate-400">Modalidade:</span>
                <span class="ml-2 text-slate-300">{{ $pedido->modalidade_entrega->label() }}</span>
            </div>
            @endif
        </div>
    </div>

    <form wire:submit="registrar">
        {{-- Tabela de itens --}}
        <x-report-card padding="p-0" class="mb-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd. Pedida</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Já Recebido</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Saldo</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Receber Agora</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($pedido->itens as $item)
                            @php
                                $recebido = (float) ($jaRecebidoPorItem[$item->id] ?? 0);
                                $saldo = (float) $item->quantidade - $recebido;
                            @endphp
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3 text-slate-200">
                                    {{ $item->descricao }}
                                    @if($item->destino)
                                        <span class="block text-xs text-slate-500">{{ $item->destino }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format($item->quantidade, 3, ',', '.') }} {{ $item->unidade_medida }}</td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format($recebido, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right {{ $saldo <= 0 ? 'font-medium text-emerald-400' : 'text-slate-300' }}">
                                    {{ $saldo <= 0 ? 'Completo' : number_format($saldo, 3, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($saldo > 0)
                                        <input
                                            type="number"
                                            wire:model="quantidades.{{ $item->id }}"
                                            step="0.001"
                                            min="0"
                                            max="{{ $saldo }}"
                                            class="input-dark w-28 text-right"
                                            placeholder="0,000"
                                        />
                                        @if($controlaLote[$item->id] ?? false)
                                            <div class="mt-2 space-y-1">
                                                <div class="text-right text-[10px] uppercase tracking-wide text-slate-500">Lote / validade</div>
                                                <input
                                                    type="text"
                                                    wire:model="lotes.{{ $item->id }}.numero_lote"
                                                    class="input-dark w-28"
                                                    placeholder="Nº do lote"
                                                />
                                                <input
                                                    type="date"
                                                    wire:model="lotes.{{ $item->id }}.validade"
                                                    class="input-dark w-28"
                                                />
                                                @error('lotes.'.$item->id.'.numero_lote')
                                                    <p class="mt-1 text-right text-xs text-rose-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>

        {{-- Observações --}}
        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-300 mb-1">Observações</label>
            <textarea wire:model="observacoes" rows="3" class="input-dark w-full" placeholder="Opcional"></textarea>
        </div>

        {{-- Ações --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('almoxarife.recebimentos.index') }}" class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Confirmar Recebimento
            </button>
        </div>
    </form>

    {{-- Histórico de recebimentos --}}
    @if($pedido->recebimentos->isNotEmpty())
        <div class="mt-8">
            <x-report-card title="Histórico de Recebimentos" icon="clock">
                <div class="space-y-3">
                    @foreach($pedido->recebimentos as $rec)
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 text-sm">
                            <div class="mb-2 flex justify-between">
                                <span class="font-medium text-slate-200">{{ $rec->recebido_em->format('d/m/Y H:i') }}</span>
                                <span class="text-slate-400">{{ $rec->almoxarife?->name }}</span>
                            </div>
                            @foreach($rec->itens as $itemRec)
                                <div class="text-slate-300">{{ $itemRec->itemPedidoCompra?->descricao }}: {{ number_format($itemRec->quantidade_recebida, 3, ',', '.') }}</div>
                            @endforeach
                            @if($rec->observacoes)
                                <div class="mt-1 italic text-slate-500">{{ $rec->observacoes }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-report-card>
        </div>
    @endif
</div>
