<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">
            {{ $requisicaoId ? 'Editar Requisição' : 'Nova Requisição' }}
        </h1>
        @if ($requisicaoId)
            <button wire:click="abrirModalCancelar" class="text-sm text-red-600 hover:text-red-800 border border-red-300 px-3 py-1.5 rounded-md">
                Cancelar Requisição
            </button>
        @endif
    </div>

    @error('formulario')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">{{ $message }}</div>
    @enderror

    {{-- Alerta de verba --}}
    @if ($percentualVerba !== null)
        <div class="mb-4 p-3 rounded-md text-sm {{ $percentualVerba >= 100 ? 'bg-red-50 border border-red-300 text-red-700' : ($percentualVerba >= 80 ? 'bg-yellow-50 border border-yellow-300 text-yellow-800' : 'bg-green-50 border border-green-300 text-green-700') }}">
            Verba da obra: <strong>{{ number_format($percentualVerba, 1) }}%</strong> consumida
            {{ $percentualVerba >= 100 ? '— submissão bloqueada.' : ($percentualVerba >= 80 ? '— atenção: próximo do limite.' : '.') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6 space-y-6">

        {{-- Dados gerais --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidade <span class="text-red-500">*</span></label>
                <select wire:model.live="unidadeId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('unidadeId') border-red-500 @enderror">
                    <option value="">Selecione...</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                    @endforeach
                </select>
                @error('unidadeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Centro de Custo <span class="text-red-500">*</span></label>
                <select wire:model="centroCustoId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('centroCustoId') border-red-500 @enderror">
                    <option value="">Selecione...</option>
                    @foreach ($centrosCusto as $cc)
                        <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nome }}</option>
                    @endforeach
                </select>
                @error('centroCustoId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Obra (opcional)</label>
                <select wire:model.live="obraId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Nenhuma</option>
                    @foreach ($obras as $obra)
                        <option value="{{ $obra->id }}">{{ $obra->id }} — {{ $obra->unidade->nome ?? '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-6 pt-5">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model="urgente" class="rounded">
                    Urgente
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model.live="isEmergencial" class="rounded">
                    Emergencial
                </label>
            </div>
        </div>

        @if ($isEmergencial)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa da emergência <span class="text-red-500">*</span></label>
                <textarea wire:model="justificativa" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('justificativa') border-red-500 @enderror" placeholder="Descreva o motivo da compra emergencial..."></textarea>
                @error('justificativa') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Itens --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-700">Itens da Requisição</h2>
                <button wire:click="adicionarItem" type="button" class="text-sm text-blue-600 hover:text-blue-800">+ Adicionar item</button>
            </div>

            {{-- Sec P2-02: busca server-side — filtra até 50 itens do catálogo sem carregar tudo --}}
            <div class="mb-3">
                <input wire:model.live="buscaCatalogo" type="text" placeholder="Filtrar catálogo de itens..."
                    class="w-full max-w-sm border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            @error('itens') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="space-y-3">
                @foreach ($itens as $idx => $item)
                    <div class="border border-gray-200 rounded-md p-3 space-y-2">
                        <div class="flex gap-2 items-start">
                            <div class="w-56">
                                <select wire:change="selecionarItemCatalogo({{ $idx }}, $event.target.value || null)"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Item avulso (descrição livre)</option>
                                    @foreach ($itensCatalogo as $catalogoItem)
                                        <option value="{{ $catalogoItem->id }}" @selected(($item['item_catalogo_id'] ?? null) == $catalogoItem->id)>
                                            {{ $catalogoItem->codigo ? $catalogoItem->codigo.' — ' : '' }}{{ $catalogoItem->descricao }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1 min-w-0">
                                <input wire:model.live="itens.{{ $idx }}.descricao" type="text" placeholder="Descrição do item"
                                    @disabled(! ($item['avulso'] ?? true))
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 @error('itens.'.$idx.'.descricao') border-red-500 @enderror">
                                @error('itens.'.$idx.'.descricao') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('itens.'.$idx.'.item_catalogo_id') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="w-24">
                                <input wire:model.live="itens.{{ $idx }}.quantidade" type="number" step="0.001" min="0.001" placeholder="Qtd"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('itens.'.$idx.'.quantidade') border-red-500 @enderror">
                            </div>
                            <div class="w-16">
                                <input wire:model="itens.{{ $idx }}.unidade_medida" type="text" placeholder="Un"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="w-32">
                                <input wire:model.live="itens.{{ $idx }}.valor_unitario_estimado" type="number" step="0.01" min="0" placeholder="R$ unit."
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            @if (count($itens) > 1)
                                <button wire:click="removerItem({{ $idx }})" type="button" class="text-red-400 hover:text-red-600 mt-2">✕</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Ações --}}
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="{{ route('requisicoes.index') }}" class="text-sm text-gray-600 hover:text-gray-800 px-4 py-2 border border-gray-300 rounded-md">
                Voltar
            </a>
            <button wire:click="salvar" class="text-sm text-gray-700 px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                Salvar rascunho
            </button>
            <button wire:click="submeter" wire:loading.attr="disabled"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-md disabled:opacity-50"
                @if ($percentualVerba !== null && $percentualVerba >= 100) disabled @endif>
                Submeter requisição
            </button>
        </div>
    </div>

    {{-- Modal cancelar --}}
    @if ($mostrarModalCancelar)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Cancelar Requisição</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-red-500">*</span></label>
                    <textarea wire:model="motivoCancelamento" rows="3"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('motivoCancelamento') border-red-500 @enderror"></textarea>
                    @error('motivoCancelamento') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button wire:click="$set('mostrarModalCancelar', false)" class="text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50">
                        Voltar
                    </button>
                    <button wire:click="cancelarRequisicao" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Confirmar cancelamento
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
