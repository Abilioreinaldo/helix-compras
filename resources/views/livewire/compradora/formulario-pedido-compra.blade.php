<div class="report-canvas">
    <x-page-header
        :title="$pedido->numero ? 'Pedido ' . $pedido->numero : 'Rascunho de Pedido de Compra'"
        icon="cart"
        :subtitle="'Fornecedor: ' . $pedido->fornecedor->razao_social"
    />

    @if(session('sucesso'))
        <div class="mb-4 border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 rounded-lg px-4 py-3 text-sm">{{ session('sucesso') }}</div>
    @endif

    @error('emissao')
        <div class="mb-4 border border-rose-500/30 bg-rose-500/10 text-rose-300 rounded-lg px-4 py-3 text-sm">{{ $message }}</div>
    @enderror

    @error('cancelamento')
        <div class="mb-4 border border-rose-500/30 bg-rose-500/10 text-rose-300 rounded-lg px-4 py-3 text-sm">{{ $message }}</div>
    @enderror

    <x-report-card title="Dados do Pedido">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Condições de Pagamento</label>
                <textarea wire:model="condicoesPagamento" rows="2" class="input-dark w-full"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Observações</label>
                <textarea wire:model="observacoes" rows="2" class="input-dark w-full"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Prazo de Entrega</label>
                <input type="date" wire:model="prazoEntrega" class="input-dark w-full" />
                @error('prazoEntrega') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Modalidade de Entrega</label>
                <select wire:model="modalidadeEntrega" class="input-dark w-full">
                    <option value="">— Selecione —</option>
                    <option value="entrega">Entrega pelo fornecedor</option>
                    <option value="retirada">Retirada pelo comprador</option>
                    <option value="transportadora">Via transportadora</option>
                </select>
                @error('modalidadeEntrega') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-report-card>

    <x-report-card title="Itens" padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-4 py-2.5 text-center text-xs font-medium uppercase tracking-wide text-slate-500 w-20">Qtd</th>
                        <th class="px-4 py-2.5 text-center text-xs font-medium uppercase tracking-wide text-slate-500 w-16">Un</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500 w-36">Valor Unit. (R$)</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500 w-32">Total (R$)</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Destino</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach($itens as $index => $item)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 text-slate-200">{{ $item['descricao'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-300">{{ $item['quantidade'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-500">{{ $item['unidade_medida'] }}</td>
                            <td class="px-4 py-3">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.blur="itens.{{ $index }}.valor_unitario"
                                    wire:change="atualizarTotal({{ $index }})"
                                    class="input-dark w-full"
                                />
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-300">
                                R$ {{ number_format((float)$item['valor_total'], 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    type="text"
                                    wire:model.blur="itens.{{ $index }}.destino"
                                    placeholder="Ex: Unidade Centro"
                                    class="input-dark w-full"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-report-card>

    <div class="flex flex-wrap gap-3 pt-2">
        <button wire:click="salvar" class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 border border-zinc-700 hover:bg-zinc-700">
            Salvar Rascunho
        </button>
        <button wire:click="emitir" wire:confirm="Emitir o pedido? Esta ação é irreversível." class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
            Emitir Pedido
        </button>
        <button wire:click="$set('mostrarModalCancelar', true)" class="rounded-lg px-4 py-2 text-sm font-medium text-rose-400 hover:text-rose-300 border border-zinc-700 bg-zinc-800 hover:bg-zinc-700">
            Cancelar PC
        </button>
        <a href="{{ route('compradora.pedidos.index') }}" class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 border border-zinc-700 hover:bg-zinc-700">
            Voltar
        </a>
    </div>

    @if($mostrarModalCancelar)
        <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-semibold text-slate-100 mb-4">Cancelar Pedido de Compra</h3>
                <label class="block text-sm font-medium text-slate-300 mb-1">Motivo do cancelamento</label>
                <textarea wire:model="motivoCancelamento" rows="3" placeholder="Motivo do cancelamento..." class="input-dark w-full mb-4"></textarea>
                @error('cancelamento') <p class="mt-1 text-sm text-rose-400 mb-2">{{ $message }}</p> @enderror
                <div class="flex gap-2 justify-end">
                    <button wire:click="$set('mostrarModalCancelar', false)" class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 border border-zinc-700 hover:bg-zinc-700">
                        Fechar
                    </button>
                    <button wire:click="cancelar" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500">
                        Confirmar Cancelamento
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
