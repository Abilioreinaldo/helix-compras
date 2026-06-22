<div class="report-canvas">
    <x-page-header title="Centros de Custo" icon="tag" subtitle="Gerencie os centros de custo por unidade e seus gestores responsáveis." />

    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="min-w-[220px] flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por código ou nome..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Unidade">
            <select wire:model.live="filtroUnidade" class="input-dark">
                <option value="">Todas as unidades</option>
                @foreach ($unidades as $unidade)
                    <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <div class="flex items-end">
            <button wire:click="abrirCriar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Novo Centro de Custo
            </button>
        </div>
    </x-filter-bar>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Nome</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Gestor</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ativo</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($centros as $centro)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 font-mono text-slate-300">{{ $centro->codigo }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $centro->nome }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $centro->unidade?->nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $centro->gestor?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $centro->ativo ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-300' }}">
                                    {{ $centro->ativo ? 'Sim' : 'Não' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button wire:click="abrirEditar({{ $centro->id }})" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-zinc-700 transition-colors">Editar</button>
                                <button wire:click="excluir({{ $centro->id }})" wire:confirm="Confirma exclusão?" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-500 transition-colors">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Nenhum centro de custo encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-zinc-800">
            {{ $centros->links() }}
        </div>
    </x-report-card>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Centro de Custo' : 'Novo Centro de Custo' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Unidade</label>
                        <select wire:model="unidadeId" class="input-dark w-full @error('unidadeId') border-rose-500 @enderror">
                            <option value="">Selecione...</option>
                            @foreach ($unidades as $unidade)
                                <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('unidadeId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Código</label>
                        <input type="text" wire:model="codigo" maxlength="30" class="input-dark w-full @error('codigo') border-rose-500 @enderror">
                        @error('codigo') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Nome</label>
                        <input type="text" wire:model="nome" class="input-dark w-full @error('nome') border-rose-500 @enderror">
                        @error('nome') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
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
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" wire:model="ativo" class="rounded border-zinc-700 bg-zinc-800">
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
</div>
