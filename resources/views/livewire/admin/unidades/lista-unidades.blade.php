<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Unidades</h1>
        <button wire:click="abrirCriar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Nova Unidade
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex gap-3 mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="busca"
            placeholder="Buscar por nome..."
            class="border border-gray-300 rounded-md px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
        <select wire:model.live="filtroTipo" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos os tipos</option>
            @foreach ($tiposUnidade as $tipo)
                <option value="{{ $tipo->value }}">{{ ucfirst($tipo->value) }}</option>
            @endforeach
        </select>
        <select wire:model.live="filtroStatus" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos os status</option>
            @foreach ($statusUnidade as $s)
                <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gestor</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($unidades as $unidade)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $unidade->nome }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($unidade->tipo->value) }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $unidade->status->value === 'ativa' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($unidade->status->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $unidade->gestor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            <button wire:click="abrirEditar({{ $unidade->id }})" class="text-blue-600 hover:text-blue-800">Editar</button>
                            <button wire:click="excluir({{ $unidade->id }})" wire:confirm="Confirma exclusão?" class="text-red-600 hover:text-red-800">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Nenhuma unidade encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $unidades->links() }}
        </div>
    </div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editandoId ? 'Editar Unidade' : 'Nova Unidade' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input type="text" wire:model="nome" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nome') border-red-500 @enderror">
                        @error('nome') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select wire:model.live="tipo" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('tipo') border-red-500 @enderror">
                            <option value="">Selecione...</option>
                            @foreach ($tiposUnidade as $t)
                                <option value="{{ $t->value }}">{{ ucfirst($t->value) }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ (somente dígitos)</label>
                        <input type="text" wire:model="cnpj" maxlength="14" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                        <input type="text" wire:model="endereco" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model="status" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach ($statusUnidade as $s)
                                <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Campos adicionais para Obra --}}
                    @if ($tipo === 'obra')
                        <hr class="border-gray-200">
                        <p class="text-sm font-semibold text-gray-700">Dados da Obra</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Verba (R$)</label>
                            <input type="number" wire:model="obraVerba" min="0" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Iniciada em</label>
                            <input type="date" wire:model="obraIniciadaEm" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('obraIniciadaEm') border-red-500 @enderror">
                            @error('obraIniciadaEm') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Previsão de término</label>
                            <input type="date" wire:model="obraPrevisaoTermino" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    @endif
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
