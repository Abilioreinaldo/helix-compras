<div class="report-canvas">
    <x-page-header
        :title="$requisicaoId ? 'Editar Requisição' : 'Nova Requisição'"
        icon="document"
        subtitle="Preencha os dados e itens da requisição de compra."
    />

    {{-- Erro de formulário geral --}}
    @error('formulario')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-400">{{ $message }}</div>
    @enderror

    {{-- Alerta de verba --}}
    @if ($percentualVerba !== null)
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm
            {{ $percentualVerba >= 100
                ? 'border-rose-500/30 bg-rose-500/10 text-rose-400'
                : ($percentualVerba >= 80
                    ? 'border-amber-500/30 bg-amber-500/10 text-amber-300'
                    : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400') }}">
            Verba da obra: <strong>{{ number_format($percentualVerba, 1) }}%</strong> consumida
            {{ $percentualVerba >= 100 ? '— submissão bloqueada.' : ($percentualVerba >= 80 ? '— atenção: próximo do limite.' : '.') }}
        </div>
    @endif

    {{-- Card: Dados Gerais --}}
    <x-report-card title="Dados Gerais">
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Unidade <span class="text-rose-400">*</span></label>
                    <select wire:model.live="unidadeId" class="input-dark w-full @error('unidadeId') ring-1 ring-rose-500 @enderror">
                        <option value="">Selecione...</option>
                        @foreach ($unidades as $unidade)
                            <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                        @endforeach
                    </select>
                    @error('unidadeId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Centro de Custo <span class="text-rose-400">*</span></label>
                    <select wire:model="centroCustoId" class="input-dark w-full @error('centroCustoId') ring-1 ring-rose-500 @enderror">
                        <option value="">Selecione...</option>
                        @foreach ($centrosCusto as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nome }}</option>
                        @endforeach
                    </select>
                    @error('centroCustoId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Obra (opcional)</label>
                    <select wire:model.live="obraId" class="input-dark w-full">
                        <option value="">Nenhuma</option>
                        @foreach ($obras as $obra)
                            <option value="{{ $obra->id }}">{{ $obra->id }} — {{ $obra->unidade->nome ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-6 pt-5">
                    <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                        <input type="checkbox" wire:model="urgente" class="rounded border-zinc-700 bg-zinc-800 text-emerald-600">
                        Urgente
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                        <input type="checkbox" wire:model.live="isEmergencial" class="rounded border-zinc-700 bg-zinc-800 text-emerald-600">
                        Emergencial
                    </label>
                </div>
            </div>

            @if ($isEmergencial)
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Justificativa da emergência <span class="text-rose-400">*</span></label>
                    <textarea wire:model="justificativa" rows="3"
                        class="input-dark w-full @error('justificativa') ring-1 ring-rose-500 @enderror"
                        placeholder="Descreva o motivo da compra emergencial..."></textarea>
                    @error('justificativa') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    </x-report-card>

    {{-- Card: Itens da Requisição --}}
    <x-report-card title="Itens da Requisição">
        <div class="space-y-4">
            @if ($this->previewExpressa())
                <div class="rounded-lg border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-300">
                    ⚡ <strong>Via expressa:</strong> todos os itens têm preço homologado — esta requisição dispensa a cotação e segue direto para aprovação após a triagem.
                </div>
            @endif
            {{-- Sec P2-02: busca server-side — filtra até 50 itens do catálogo sem carregar tudo --}}
            <div class="flex items-center justify-between gap-4">
                <div class="flex-1 max-w-sm">
                    <input wire:model.live="buscaCatalogo" type="text" placeholder="Filtrar catálogo de itens..."
                        class="input-dark w-full">
                </div>
                <button wire:click="adicionarItem" type="button"
                    class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                    + Adicionar item
                </button>
            </div>

            @error('itens') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror

            <div class="space-y-3">
                @foreach ($itens as $idx => $item)
                    <div wire:key="item-{{ $idx }}" class="rounded-lg border border-zinc-800 bg-zinc-950/40 p-4 space-y-3">
                        <div class="flex flex-wrap gap-3 items-start">
                            {{-- Seletor de catálogo --}}
                            <div class="w-56 shrink-0">
                                <label class="block text-xs font-medium text-slate-400 mb-1">Catálogo</label>
                                <select wire:change="selecionarItemCatalogo({{ $idx }}, $event.target.value || null)"
                                    class="input-dark w-full">
                                    <option value="">Item avulso (descrição livre)</option>
                                    @foreach ($itensCatalogo as $catalogoItem)
                                        <option value="{{ $catalogoItem->id }}" @selected(($item['item_catalogo_id'] ?? null) == $catalogoItem->id)>
                                            {{ $catalogoItem->codigo ? $catalogoItem->codigo.' — ' : '' }}{{ $catalogoItem->descricao }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Descrição --}}
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-medium text-slate-400 mb-1">Descrição <span class="text-rose-400">*</span></label>
                                <input wire:model.live="itens.{{ $idx }}.descricao" type="text" placeholder="Descrição do item"
                                    @disabled(! ($item['avulso'] ?? true))
                                    class="input-dark w-full disabled:opacity-50 disabled:cursor-not-allowed @error('itens.'.$idx.'.descricao') ring-1 ring-rose-500 @enderror">
                                @error('itens.'.$idx.'.descricao') <p class="mt-0.5 text-xs text-rose-400">{{ $message }}</p> @enderror
                                @error('itens.'.$idx.'.item_catalogo_id') <p class="mt-0.5 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Quantidade --}}
                            <div class="w-24 shrink-0">
                                <label class="block text-xs font-medium text-slate-400 mb-1">Qtd <span class="text-rose-400">*</span></label>
                                <input wire:model.live="itens.{{ $idx }}.quantidade" type="number" step="0.001" min="0.001" placeholder="Qtd"
                                    class="input-dark w-full @error('itens.'.$idx.'.quantidade') ring-1 ring-rose-500 @enderror">
                                @error('itens.'.$idx.'.quantidade') <p class="mt-0.5 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Unidade de medida --}}
                            <div class="w-16 shrink-0">
                                <label class="block text-xs font-medium text-slate-400 mb-1">Un.</label>
                                <input wire:model="itens.{{ $idx }}.unidade_medida" type="text" placeholder="Un"
                                    class="input-dark w-full">
                            </div>

                            {{-- Valor unitário --}}
                            <div class="w-32 shrink-0">
                                <label class="block text-xs font-medium text-slate-400 mb-1">R$ unit.</label>
                                <input wire:model.live="itens.{{ $idx }}.valor_unitario_estimado" type="number" step="0.01" min="0" placeholder="R$ unit."
                                    class="input-dark w-full">
                            </div>

                            {{-- Remover item --}}
                            @if (count($itens) > 1)
                                <div class="shrink-0 pt-5">
                                    <button wire:click="removerItem({{ $idx }})" type="button"
                                        class="text-rose-400 hover:text-rose-300 transition-colors p-1" title="Remover item">
                                        ✕
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-report-card>

    {{-- Ações --}}
    <div class="flex items-center justify-between gap-3 pt-2">
        <div>
            @if ($requisicaoId)
                <button wire:click="abrirModalCancelar" type="button"
                    class="rounded-lg border border-rose-800/50 px-4 py-2 text-sm font-medium text-rose-400 hover:bg-rose-500/10 transition-colors">
                    Cancelar Requisição
                </button>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('requisicoes.index') }}"
                class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                Voltar
            </a>
            <button wire:click="salvar" type="button"
                class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                Salvar rascunho
            </button>
            <button wire:click="submeter" wire:loading.attr="disabled" type="button"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors disabled:opacity-50"
                @if ($percentualVerba !== null && $percentualVerba >= 100) disabled @endif>
                Submeter requisição
            </button>
        </div>
    </div>

    {{-- Modal cancelar --}}
    @if ($mostrarModalCancelar)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">Cancelar Requisição</h2>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Motivo <span class="text-rose-400">*</span></label>
                    <textarea wire:model="motivoCancelamento" rows="3"
                        class="input-dark w-full @error('motivoCancelamento') ring-1 ring-rose-500 @enderror"></textarea>
                    @error('motivoCancelamento') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button wire:click="$set('mostrarModalCancelar', false)" type="button"
                        class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                        Voltar
                    </button>
                    <button wire:click="cancelarRequisicao" type="button"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                        Confirmar cancelamento
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
