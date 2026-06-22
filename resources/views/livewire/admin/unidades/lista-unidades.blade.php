<div class="report-canvas">
    <div class="flex items-center justify-between mb-6">
        <x-page-header title="Unidades" icon="building" subtitle="Gerenciamento de unidades cadastradas no sistema." />
        <button wire:click="abrirCriar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
            Nova Unidade
        </button>
    </div>

    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por nome..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Tipo">
            <select wire:model.live="filtroTipo" class="input-dark w-full">
                <option value="">Todos os tipos</option>
                @foreach ($tiposUnidade as $tipo)
                    <option value="{{ $tipo->value }}">{{ ucfirst($tipo->value) }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Status">
            <select wire:model.live="filtroStatus" class="input-dark w-full">
                <option value="">Todos os status</option>
                @foreach ($statusUnidade as $s)
                    <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Nome</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Tipo</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Gestor</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($unidades as $unidade)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 text-slate-300">{{ $unidade->nome }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ ucfirst($unidade->tipo->value) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $unidade->status->value === 'ativa' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                                    {{ ucfirst($unidade->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $unidade->gestor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button wire:click="abrirEditar({{ $unidade->id }})" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-zinc-700 transition-colors">Editar</button>
                                <button wire:click="excluir({{ $unidade->id }})" wire:confirm="Confirma exclusão?" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-500 transition-colors">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Nenhuma unidade encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-zinc-800">
            {{ $unidades->links() }}
        </div>
    </x-report-card>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Unidade' : 'Nova Unidade' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Nome</label>
                        <input type="text" wire:model="nome" class="input-dark w-full @error('nome') border-rose-500 @enderror">
                        @error('nome') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Tipo</label>
                        <select wire:model.live="tipo" class="input-dark w-full @error('tipo') border-rose-500 @enderror">
                            <option value="">Selecione...</option>
                            @foreach ($tiposUnidade as $t)
                                <option value="{{ $t->value }}">{{ ucfirst($t->value) }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">CNPJ (somente dígitos)</label>
                        <input type="text" wire:model="cnpj" maxlength="14" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Endereço</label>
                        <input type="text" wire:model="endereco" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Gestor</label>
                        <select wire:model="gestorId" class="input-dark w-full">
                            <option value="">Sem gestor</option>
                            @foreach ($usuarios as $usuario)
                                <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                        <select wire:model="status" class="input-dark w-full">
                            @foreach ($statusUnidade as $s)
                                <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Campos adicionais para Obra --}}
                    @if ($tipo === 'obra')
                        <hr class="border-zinc-700">
                        <p class="text-sm font-semibold text-slate-300">Dados da Obra</p>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Verba (R$)</label>
                            <input type="number" wire:model="obraVerba" min="0" step="0.01" class="input-dark w-full">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Iniciada em</label>
                            <input type="date" wire:model="obraIniciadaEm" class="input-dark w-full @error('obraIniciadaEm') border-rose-500 @enderror">
                            @error('obraIniciadaEm') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Previsão de término</label>
                            <input type="date" wire:model="obraPrevisaoTermino" class="input-dark w-full">
                        </div>
                    @endif
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
