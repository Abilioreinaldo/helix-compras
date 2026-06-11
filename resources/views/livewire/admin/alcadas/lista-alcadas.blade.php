<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Alçadas</h1>
        <button wire:click="abrirCriar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Nova Alçada
        </button>
    </div>

    {{-- Lista de faixas --}}
    <div class="space-y-4">
        @forelse ($faixas as $faixa)
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">{{ $faixa->nome }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            R$ {{ number_format($faixa->valor_minimo, 2, ',', '.') }}
                            @if ($faixa->valor_maximo)
                                — R$ {{ number_format($faixa->valor_maximo, 2, ',', '.') }}
                            @else
                                — sem teto
                            @endif
                            @if ($faixa->is_emergencial)
                                <span class="ml-2 inline-flex px-1.5 py-0.5 rounded text-xs bg-orange-100 text-orange-700">Emergencial</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="abrirEditar({{ $faixa->id }})" class="text-blue-600 hover:text-blue-800 text-sm">Editar</button>
                        <button wire:click="excluir({{ $faixa->id }})" wire:confirm="Confirma exclusão desta faixa e todas as suas etapas?" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                    </div>
                </div>

                @if ($faixa->etapas->isNotEmpty())
                    <div class="mt-3 space-y-1">
                        @foreach ($faixa->etapas as $etapa)
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-medium">{{ $etapa->ordem }}</span>
                                <span>{{ ucfirst($etapa->nivel_exigido->value) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-lg shadow p-8 text-center text-sm text-gray-500">
                Nenhuma faixa de alçada cadastrada.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $faixas->links() }}</div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editandoId ? 'Editar Alçada' : 'Nova Alçada' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input type="text" wire:model="nome" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nome') border-red-500 @enderror">
                        @error('nome') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor Mínimo (R$)</label>
                            <input type="number" wire:model="valorMinimo" min="0" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('valorMinimo') border-red-500 @enderror">
                            @error('valorMinimo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor Máximo (R$)</label>
                            <input type="number" wire:model="valorMaximo" min="0" step="0.01" placeholder="Sem teto" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('valorMaximo') border-red-500 @enderror">
                            @error('valorMaximo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isEmergencial" class="rounded border-gray-300">
                            Faixa emergencial
                        </label>
                    </div>

                    {{-- Etapas --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Etapas de aprovação</label>
                            <button wire:click="adicionarEtapa" type="button" class="text-xs text-blue-600 hover:text-blue-800">+ Adicionar etapa</button>
                        </div>

                        @forelse ($etapas as $indice => $etapa)
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-medium flex-shrink-0">{{ $indice + 1 }}</span>
                                <select wire:model="etapas.{{ $indice }}.nivel_exigido" class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @foreach ($niveisAlcada as $nivel)
                                        <option value="{{ $nivel->value }}">{{ ucfirst($nivel->value) }}</option>
                                    @endforeach
                                </select>
                                <button wire:click="removerEtapa({{ $indice }})" type="button" class="text-red-500 hover:text-red-700 text-sm">Remover</button>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">Nenhuma etapa adicionada.</p>
                        @endforelse
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
