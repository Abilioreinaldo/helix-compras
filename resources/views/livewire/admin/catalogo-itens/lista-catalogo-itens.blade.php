<div class="report-canvas">
    <x-page-header title="Catálogo de Itens" icon="book" subtitle="Gerencie os itens disponíveis para requisição e seus parâmetros de controle." />

    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="min-w-[220px] flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por descrição ou código..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Status">
            <select wire:model.live="filtroAtivo" class="input-dark">
                <option value="">Todos (ativo)</option>
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
            </select>
        </x-filter-bar.field>
        <div class="flex items-end">
            <button wire:click="abrirCriar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Novo Item
            </button>
        </div>
    </x-filter-bar>

    @error('excluir')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Categoria</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ativo</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Lote</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($itens as $item)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 font-mono text-slate-300">{{ $item->codigo ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $item->descricao }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $item->unidade_medida ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $item->categoria ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $item->ativo ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-300' }}">
                                    {{ $item->ativo ? 'Sim' : 'Não' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    wire:click="alternarControleLote({{ $item->id }})"
                                    wire:confirm="{{ $item->controla_lote ? 'Desligar o controle de lote deste item?' : 'Ligar o controle de lote deste item?' }}"
                                    type="button"
                                    class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $item->controla_lote ? 'bg-amber-500/15 text-amber-400 hover:bg-amber-500/25' : 'bg-slate-500/15 text-slate-300 hover:bg-slate-500/25' }} transition-colors"
                                >
                                    {{ $item->controla_lote ? 'Controla lote' : 'Sem controle' }}
                                </button>
                                @error("controla_lote_{$item->id}")
                                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button wire:click="abrirModalMinimos({{ $item->id }})" class="text-emerald-400 hover:text-emerald-300 transition-colors">Mínimos</button>
                                <button wire:click="abrirEditar({{ $item->id }})" class="text-emerald-400 hover:text-emerald-300 transition-colors">Editar</button>
                                <button wire:click="excluir({{ $item->id }})" wire:confirm="Confirma exclusão?" class="text-rose-400 hover:text-rose-300 transition-colors">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Nenhum item de catálogo encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-800 px-4 py-3">
            {{ $itens->links() }}
        </div>
    </x-report-card>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Item' : 'Novo Item' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Descrição</label>
                        <input type="text" wire:model="descricao" class="input-dark w-full @error('descricao') border-rose-500 @enderror">
                        @error('descricao') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Código</label>
                        <input type="text" wire:model="codigo" class="input-dark w-full @error('codigo') border-rose-500 @enderror">
                        @error('codigo') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Unidade de Medida</label>
                        <input type="text" wire:model="unidadeMedida" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Categoria</label>
                        <input type="text" wire:model="categoria" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                            <input type="checkbox" wire:model="ativo" class="rounded border-zinc-600 bg-zinc-800 text-emerald-500 focus:ring-emerald-500/40">
                            Ativo
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="$set('mostrarModal', false)" class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="salvar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Mínimos por Unidade --}}
    @if ($mostrarModalMinimos)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-2xl p-6 overflow-y-auto max-h-[90vh]">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-100">Estoques Mínimos por Unidade</h2>
                    <button wire:click="fecharModalMinimos" class="text-slate-400 hover:text-slate-200 text-xl font-bold leading-none transition-colors">&times;</button>
                </div>
                <p class="text-sm text-slate-400 mb-4">Item: <strong class="text-slate-200">{{ $minimoItemDescricao }}</strong></p>

                @if (empty($minimosPorUnidade))
                    <p class="text-sm text-slate-500">Nenhuma unidade cadastrada.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($minimosPorUnidade as $idx => $minimo)
                            <div class="flex items-center gap-3 py-2 border-b border-zinc-800">
                                <span class="flex-1 text-sm text-slate-300">{{ $minimo['nome'] }}</span>
                                <input
                                    wire:model="minimosPorUnidade.{{ $idx }}.quantidade_minima"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    placeholder="Qtd mínima (0 = sem mínimo)"
                                    class="input-dark w-40"
                                />
                                <button
                                    wire:click="salvarMinimoUnidade({{ $minimo['unidade_id'] }})"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 transition-colors"
                                >
                                    Salvar
                                </button>
                            </div>
                            @error("minimo_{$minimo['unidade_id']}")
                                <p class="text-xs text-rose-400 mt-0.5">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end mt-6">
                    <button wire:click="fecharModalMinimos" class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700 transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
