<div class="report-canvas">
    <x-page-header title="Alçadas" icon="scale" subtitle="Faixas de valor e etapas de aprovação para requisições de compra." />

    {{-- Lista de faixas --}}
    <div class="space-y-4">
        @forelse ($faixas as $faixa)
            <x-report-card>
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-200">{{ $faixa->nome }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">
                            R$ {{ number_format($faixa->valor_minimo, 2, ',', '.') }}
                            @if ($faixa->valor_maximo)
                                — R$ {{ number_format($faixa->valor_maximo, 2, ',', '.') }}
                            @else
                                — sem teto
                            @endif
                            @if ($faixa->is_emergencial)
                                <span class="ml-2 inline-flex rounded px-1.5 py-0.5 text-xs bg-amber-500/15 text-amber-400">Emergencial</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="abrirEditar({{ $faixa->id }})" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-zinc-700 transition-colors">Editar</button>
                        <button wire:click="excluir({{ $faixa->id }})" wire:confirm="Confirma exclusão desta faixa e todas as suas etapas?" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-500 transition-colors">Excluir</button>
                    </div>
                </div>

                @if ($faixa->etapas->isNotEmpty())
                    <div class="mt-3 space-y-1">
                        @foreach ($faixa->etapas as $etapa)
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-xs font-medium text-emerald-400">{{ $etapa->ordem }}</span>
                                <span>{{ ucfirst($etapa->nivel_exigido->value) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-report-card>
        @empty
            <x-empty-state
                icon="scale"
                title="Nenhuma faixa de alçada cadastrada"
                message="Crie a primeira faixa clicando em Nova Alçada."
            />
        @endforelse
    </div>

    <div class="mt-4">{{ $faixas->links() }}</div>

    {{-- Botão Nova Alçada --}}
    <div class="mt-6 flex justify-end">
        <button wire:click="abrirCriar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
            Nova Alçada
        </button>
    </div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Alçada' : 'Nova Alçada' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Nome</label>
                        <input type="text" wire:model="nome" class="input-dark w-full @error('nome') border-rose-500 @enderror">
                        @error('nome') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Valor Mínimo (R$)</label>
                            <input type="number" wire:model="valorMinimo" min="0" step="0.01" class="input-dark w-full @error('valorMinimo') border-rose-500 @enderror">
                            @error('valorMinimo') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Valor Máximo (R$)</label>
                            <input type="number" wire:model="valorMaximo" min="0" step="0.01" placeholder="Sem teto" class="input-dark w-full @error('valorMaximo') border-rose-500 @enderror">
                            @error('valorMaximo') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" wire:model="isEmergencial" class="rounded border-zinc-700">
                            Faixa emergencial
                        </label>
                    </div>

                    {{-- Etapas --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-slate-300">Etapas de aprovação</label>
                            <button wire:click="adicionarEtapa" type="button" class="text-xs text-emerald-400 hover:text-emerald-300">+ Adicionar etapa</button>
                        </div>

                        @forelse ($etapas as $indice => $etapa)
                            <div class="flex items-center gap-2 mb-2">
                                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-xs font-medium text-emerald-400">{{ $indice + 1 }}</span>
                                <select wire:model="etapas.{{ $indice }}.nivel_exigido" class="input-dark flex-1">
                                    @foreach ($niveisAlcada as $nivel)
                                        <option value="{{ $nivel->value }}">{{ ucfirst($nivel->value) }}</option>
                                    @endforeach
                                </select>
                                <button wire:click="removerEtapa({{ $indice }})" type="button" class="text-sm text-rose-400 hover:text-rose-300">Remover</button>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">Nenhuma etapa adicionada.</p>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="$set('mostrarModal', false)" class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="salvar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
