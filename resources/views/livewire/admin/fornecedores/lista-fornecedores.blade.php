<div class="report-canvas">
    <x-page-header title="Fornecedores" icon="truck" subtitle="Cadastro e homologação de fornecedores." />

    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="min-w-[260px] flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por razão social ou CNPJ..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Ativo">
            <select wire:model.live="filtroAtivo" class="input-dark">
                <option value="">Todos (ativo)</option>
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Homologado">
            <select wire:model.live="filtroHomologado" class="input-dark">
                <option value="">Todos (homologado)</option>
                <option value="1">Homologado</option>
                <option value="0">Pendente</option>
            </select>
        </x-filter-bar.field>
        <div class="flex items-end">
            <button wire:click="abrirCriar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Novo Fornecedor
            </button>
        </div>
    </x-filter-bar>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Razão Social</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">CNPJ</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Categoria</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Homologado</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ativo</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($fornecedores as $fornecedor)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 text-slate-300">
                                {{ $fornecedor->razao_social }}
                                @if ($fornecedor->nome_fantasia)
                                    <span class="block text-xs text-slate-500">{{ $fornecedor->nome_fantasia }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-400">{{ $fornecedor->cnpj }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $fornecedor->categoria ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($fornecedor->homologado)
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-400">Sim</span>
                                @else
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-slate-500/15 text-slate-300">Pendente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $fornecedor->ativo ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-300' }}">
                                    {{ $fornecedor->ativo ? 'Sim' : 'Não' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                @unless ($fornecedor->homologado)
                                    <button wire:click="homologar({{ $fornecedor->id }})" wire:confirm="Homologar este fornecedor?" class="text-emerald-400 hover:text-emerald-300 text-xs">Homologar</button>
                                @endunless
                                <button wire:click="abrirEditar({{ $fornecedor->id }})" class="text-emerald-400 hover:text-emerald-300 text-xs">Editar</button>
                                <button wire:click="excluir({{ $fornecedor->id }})" wire:confirm="Confirma exclusão?" class="text-rose-400 hover:text-rose-300 text-xs">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Nenhum fornecedor encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-800 px-4 py-3">
            {{ $fornecedores->links() }}
        </div>
    </x-report-card>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Fornecedor' : 'Novo Fornecedor' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Razão Social</label>
                        <input type="text" wire:model="razaoSocial" class="input-dark w-full @error('razaoSocial') border-rose-500 @enderror">
                        @error('razaoSocial') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Nome Fantasia</label>
                        <input type="text" wire:model="nomeFantasia" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">CNPJ (14 dígitos)</label>
                        <input type="text" wire:model="cnpj" maxlength="14" class="input-dark w-full @error('cnpj') border-rose-500 @enderror">
                        @error('cnpj') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Categoria</label>
                        <input type="text" wire:model="categoria" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Contato — Nome</label>
                        <input type="text" wire:model="contatoNome" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Contato — E-mail</label>
                        <input type="email" wire:model="contatoEmail" class="input-dark w-full @error('contatoEmail') border-rose-500 @enderror">
                        @error('contatoEmail') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Contato — Telefone</label>
                        <input type="text" wire:model="contatoTelefone" class="input-dark w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Observações</label>
                        <textarea wire:model="observacoes" rows="3" class="input-dark w-full"></textarea>
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
