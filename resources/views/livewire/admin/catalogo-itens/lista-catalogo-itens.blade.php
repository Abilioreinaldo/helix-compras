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
            <button wire:click="abrirCriar" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
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
                    <tr class="border-b border-slate-800 bg-slate-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Categoria</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ativo</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Lote</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($itens as $item)
                        <tr class="transition-colors hover:bg-slate-800/40">
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
                                <button wire:click="abrirModalHomologacoes({{ $item->id }})" class="text-sky-400 hover:text-sky-300 transition-colors">Preços</button>
                                <button wire:click="abrirModalMinimos({{ $item->id }})" class="text-blue-400 hover:text-blue-300 transition-colors">Mínimos</button>
                                <button wire:click="abrirEditar({{ $item->id }})" class="text-blue-400 hover:text-blue-300 transition-colors">Editar</button>
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
        <div class="border-t border-slate-800 px-4 py-3">
            {{ $itens->links() }}
        </div>
    </x-report-card>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
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
                            <input type="checkbox" wire:model="ativo" class="rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500/40">
                            Ativo
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="$set('mostrarModal', false)" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="salvar" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Mínimos por Unidade --}}
    @if ($mostrarModalMinimos)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-2xl p-6 overflow-y-auto max-h-[90vh]">
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
                            <div class="flex items-center gap-3 py-2 border-b border-slate-800">
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
                                    class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500 transition-colors"
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
                    <button wire:click="fecharModalMinimos" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Preços Homologados --}}
    @if ($mostrarModalHomologacoes)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-3xl p-6 overflow-y-auto max-h-[90vh]">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-lg font-bold text-slate-100">Preços Homologados</h2>
                    <button wire:click="fecharModalHomologacoes" class="text-slate-400 hover:text-slate-200 text-xl font-bold leading-none transition-colors">&times;</button>
                </div>
                <p class="text-sm text-slate-400 mb-4">Item: <strong class="text-slate-200">{{ $homologacaoItemDescricao }}</strong> — preço com validade dispensa a cotação ad-hoc na via expressa.</p>

                {{-- Lista das homologações existentes --}}
                @php($homologacoes = $this->homologacoesDoItem())
                @if ($homologacoes->isEmpty())
                    <p class="text-sm text-slate-500 mb-4">Nenhum preço homologado cadastrado para este item.</p>
                @else
                    <div class="mb-5 overflow-x-auto rounded-lg border border-slate-800">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-800 bg-slate-950/40 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-3 py-2">Fornecedor</th>
                                    <th class="px-3 py-2">Preço</th>
                                    <th class="px-3 py-2">Validade</th>
                                    <th class="px-3 py-2">Situação</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach ($homologacoes as $h)
                                    @php($vencida = $h->validade_fim->isPast())
                                    <tr>
                                        <td class="px-3 py-2 text-slate-300">
                                            {{ $h->fornecedor->razao_social ?? '—' }}
                                            @if ($h->preferencial) <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs font-medium bg-sky-500/15 text-sky-400">Preferencial</span> @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-slate-200">R$ {{ number_format((float) $h->preco, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-slate-400">{{ $h->validade_inicio->format('d/m/Y') }} – {{ $h->validade_fim->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2">
                                            @if (! $h->ativo)
                                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-slate-500/15 text-slate-300">Inativa</span>
                                            @elseif ($vencida)
                                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-400">Vencida</span>
                                            @else
                                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-400">Válida</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button wire:click="removerHomologacao({{ $h->id }})" wire:confirm="Remover este preço homologado?" class="text-rose-400 hover:text-rose-300 transition-colors">Remover</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Formulário de novo preço homologado --}}
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                    <h3 class="text-sm font-semibold text-slate-200 mb-3">Adicionar preço homologado</h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-300 mb-1">Fornecedor</label>
                            <select wire:model="novoFornecedorId" class="input-dark w-full @error('novoFornecedorId') border-rose-500 @enderror">
                                <option value="">Selecione…</option>
                                @foreach ($this->fornecedoresDisponiveis() as $fornecedor)
                                    <option value="{{ $fornecedor->id }}">{{ $fornecedor->razao_social }}</option>
                                @endforeach
                            </select>
                            @error('novoFornecedorId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Preço (R$)</label>
                            <input type="number" min="0" step="0.01" wire:model="novoPreco" class="input-dark w-full @error('novoPreco') border-rose-500 @enderror">
                            @error('novoPreco') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                                <input type="checkbox" wire:model="novoPreferencial" class="rounded border-slate-600 bg-slate-800 text-sky-500 focus:ring-sky-500/40">
                                Preferencial (desempate)
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Validade início</label>
                            <input type="date" wire:model="novaValidadeInicio" class="input-dark w-full @error('novaValidadeInicio') border-rose-500 @enderror">
                            @error('novaValidadeInicio') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Validade fim</label>
                            <input type="date" wire:model="novaValidadeFim" class="input-dark w-full @error('novaValidadeFim') border-rose-500 @enderror">
                            @error('novaValidadeFim') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button wire:click="adicionarHomologacao" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 transition-colors">
                            Adicionar
                        </button>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button wire:click="fecharModalHomologacoes" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
