<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Catálogo de Itens</h1>
        <button wire:click="abrirCriar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Novo Item
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex gap-3 mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="busca"
            placeholder="Buscar por descrição ou código..."
            class="border border-gray-300 rounded-md px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
        <select wire:model.live="filtroAtivo" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos (ativo)</option>
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
        </select>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($itens as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $item->codigo ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $item->descricao }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->unidade_medida ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->categoria ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $item->ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $item->ativo ? 'Sim' : 'Não' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            <button wire:click="abrirModalMinimos({{ $item->id }})" class="text-indigo-600 hover:text-indigo-800">Mínimos</button>
                            <button wire:click="abrirEditar({{ $item->id }})" class="text-blue-600 hover:text-blue-800">Editar</button>
                            <button wire:click="excluir({{ $item->id }})" wire:confirm="Confirma exclusão?" class="text-red-600 hover:text-red-800">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Nenhum item de catálogo encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $itens->links() }}
        </div>
    </div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editandoId ? 'Editar Item' : 'Novo Item' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <input type="text" wire:model="descricao" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('descricao') border-red-500 @enderror">
                        @error('descricao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                        <input type="text" wire:model="codigo" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('codigo') border-red-500 @enderror">
                        @error('codigo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidade de Medida</label>
                        <input type="text" wire:model="unidadeMedida" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <input type="text" wire:model="categoria" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model="ativo" class="rounded">
                            Ativo
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="$set('mostrarModal', false)" class="text-sm text-gray-600 hover:text-gray-800 px-4 py-2 border border-gray-300 rounded-md">
                        Cancelar
                    </button>
                    <button wire:click="salvar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Mínimos por Unidade --}}
    @if($mostrarModalMinimos)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 overflow-y-auto max-h-[90vh]">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-800">Estoques Mínimos por Unidade</h2>
                    <button wire:click="fecharModalMinimos" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none">&times;</button>
                </div>
                <p class="text-sm text-gray-500 mb-4">Item: <strong>{{ $minimoItemDescricao }}</strong></p>

                @if(empty($minimosPorUnidade))
                    <p class="text-sm text-gray-400">Nenhuma unidade cadastrada.</p>
                @else
                    <div class="space-y-2">
                        @foreach($minimosPorUnidade as $idx => $minimo)
                            <div class="flex items-center gap-3 py-2 border-b border-gray-100">
                                <span class="flex-1 text-sm text-gray-700">{{ $minimo['nome'] }}</span>
                                <input
                                    wire:model="minimosPorUnidade.{{ $idx }}.quantidade_minima"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    placeholder="Qtd mínima (0 = sem mínimo)"
                                    class="w-40 border border-gray-300 rounded-md px-2 py-1 text-sm"
                                />
                                <button
                                    wire:click="salvarMinimoUnidade({{ $minimo['unidade_id'] }})"
                                    class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                                >
                                    Salvar
                                </button>
                            </div>
                            @error("minimo_{$minimo['unidade_id']}")
                                <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end mt-6">
                    <button wire:click="fecharModalMinimos" class="px-4 py-2 text-sm bg-gray-100 rounded hover:bg-gray-200">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
