<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Centros de Custo</h1>
        <button wire:click="abrirCriar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Novo Centro de Custo
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex gap-3 mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="busca"
            placeholder="Buscar por código ou nome..."
            class="border border-gray-300 rounded-md px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
        <select wire:model.live="filtroUnidade" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todas as unidades</option>
            @foreach ($unidades as $unidade)
                <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gestor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($centros as $centro)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $centro->codigo }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $centro->nome }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $centro->unidade?->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $centro->gestor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $centro->ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $centro->ativo ? 'Sim' : 'Não' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            <button wire:click="abrirEditar({{ $centro->id }})" class="text-blue-600 hover:text-blue-800">Editar</button>
                            <button wire:click="excluir({{ $centro->id }})" wire:confirm="Confirma exclusão?" class="text-red-600 hover:text-red-800">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Nenhum centro de custo encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $centros->links() }}
        </div>
    </div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editandoId ? 'Editar Centro de Custo' : 'Novo Centro de Custo' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidade</label>
                        <select wire:model="unidadeId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('unidadeId') border-red-500 @enderror">
                            <option value="">Selecione...</option>
                            @foreach ($unidades as $unidade)
                                <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('unidadeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                        <input type="text" wire:model="codigo" maxlength="30" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('codigo') border-red-500 @enderror">
                        @error('codigo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input type="text" wire:model="nome" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nome') border-red-500 @enderror">
                        @error('nome') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gestor</label>
                        <select wire:model="gestorId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Sem gestor</option>
                            @foreach ($usuarios as $usuario)
                                <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="ativo" class="rounded border-gray-300">
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
</div>
