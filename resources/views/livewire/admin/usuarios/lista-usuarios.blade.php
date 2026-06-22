<div class="report-canvas">
    <x-page-header title="Usuários" icon="users" subtitle="Gerencie usuários, perfis e vínculos com unidades." />

    @if (session('sucesso'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('sucesso') }}
        </div>
    @endif

    @if (session('erro'))
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            {{ session('erro') }}
        </div>
    @endif

    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="min-w-[260px] flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por nome ou e-mail..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <div class="flex items-end">
            <button wire:click="abrirCriar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Novo Usuário
            </button>
        </div>
    </x-filter-bar>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Nome</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Perfil</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($usuarios as $usuario)
                        <tr class="transition-colors hover:bg-zinc-800/40">
                            <td class="px-4 py-3 text-slate-300">{{ $usuario->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $usuario->email }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @if ($usuario->is_admin)
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-violet-500/15 text-violet-400">Admin</span>
                                    @endif
                                    @if ($usuario->is_compradora)
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-sky-500/15 text-sky-400">Compradora</span>
                                    @endif
                                    @if (! $usuario->is_admin && ! $usuario->is_compradora)
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-slate-500/15 text-slate-300">Padrão</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $usuario->status === 'ativo' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                                    {{ ucfirst($usuario->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button wire:click="abrirVinculos({{ $usuario->id }})" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1.5 text-xs font-medium text-emerald-400 hover:bg-zinc-700 transition-colors">Vínculos</button>
                                <button wire:click="abrirEditar({{ $usuario->id }})" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-zinc-700 transition-colors">Editar</button>
                                <button wire:click="excluir({{ $usuario->id }})" wire:confirm="Confirma exclusão?" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1.5 text-xs font-medium text-rose-400 hover:bg-zinc-700 transition-colors">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Nenhum usuário encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-800 px-4 py-3">
            {{ $usuarios->links() }}
        </div>
    </x-report-card>

    {{-- Senha provisória gerada --}}
    @if ($senhaProvisoria)
        <div class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            Usuário criado. Senha provisória: <span class="font-mono font-semibold">{{ $senhaProvisoria }}</span>
        </div>
    @endif

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Usuário' : 'Novo Usuário' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Nome</label>
                        <input type="text" wire:model="name" class="input-dark w-full @error('name') border-rose-500 @enderror">
                        @error('name') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">E-mail</label>
                        <input type="email" wire:model="email" class="input-dark w-full @error('email') border-rose-500 @enderror">
                        @error('email') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                        <select wire:model="status" class="input-dark w-full">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>

                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" wire:model="isAdmin" class="rounded border-zinc-700 bg-zinc-800">
                            Administrador
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" wire:model="isCompradora" class="rounded border-zinc-700 bg-zinc-800">
                            Compradora
                        </label>
                    </div>

                    @if (! $editandoId)
                        <p class="text-xs text-slate-500">Uma senha provisória será gerada automaticamente e o usuário deverá trocá-la no primeiro acesso.</p>
                    @endif
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

    {{-- Modal Vínculos --}}
    @if ($mostrarModalVinculos && $usuarioVinculos)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-zinc-900 border border-zinc-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-1">Vínculos — {{ $usuarioVinculos->name }}</h2>
                <p class="text-sm text-slate-500 mb-4">Gerencie as unidades e perfis deste usuário.</p>

                {{-- Lista de vínculos existentes --}}
                @forelse ($usuarioVinculos->unidades as $unidade)
                    <div class="flex items-center justify-between py-2 border-b border-zinc-800">
                        <div>
                            <span class="text-sm font-medium text-slate-200">{{ $unidade->nome }}</span>
                            <span class="ml-2 text-xs text-slate-500">{{ $unidade->pivot->perfil }} / {{ $unidade->pivot->nivel_alcada ?? '—' }}</span>
                        </div>
                        <button wire:click="removerVinculo({{ $unidade->id }})" class="rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-1 text-xs font-medium text-rose-400 hover:bg-zinc-700 transition-colors">Remover</button>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 mb-4">Nenhum vínculo cadastrado.</p>
                @endforelse

                {{-- Adicionar novo vínculo --}}
                <div class="mt-4 space-y-3">
                    <p class="text-sm font-semibold text-slate-300">Adicionar vínculo</p>

                    <select wire:model="vincularUnidadeId" class="input-dark w-full @error('vincularUnidadeId') border-rose-500 @enderror">
                        <option value="">Selecione a unidade...</option>
                        @foreach ($todasUnidades as $u)
                            <option value="{{ $u->id }}">{{ $u->nome }}</option>
                        @endforeach
                    </select>
                    @error('vincularUnidadeId') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror

                    <select wire:model="vincularPerfil" class="input-dark w-full @error('vincularPerfil') border-rose-500 @enderror">
                        <option value="">Selecione o perfil...</option>
                        @foreach ($perfis as $p)
                            <option value="{{ $p->value }}">{{ $p->value }}</option>
                        @endforeach
                    </select>
                    @error('vincularPerfil') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror

                    <select wire:model="vincularNivelAlcada" class="input-dark w-full">
                        <option value="">Sem nível de alçada</option>
                        @foreach ($niveisAlcada as $n)
                            <option value="{{ $n->value }}">{{ ucfirst($n->value) }}</option>
                        @endforeach
                    </select>

                    <button wire:click="adicionarVinculo" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                        Adicionar
                    </button>
                </div>

                <div class="flex justify-end mt-4">
                    <button wire:click="$set('mostrarModalVinculos', false)" class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700 transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
