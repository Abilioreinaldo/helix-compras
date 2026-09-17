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
            <button wire:click="abrirCriar" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
                Convidar usuário
            </button>
        </div>
    </x-filter-bar>

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Nome</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Perfil</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($usuarios as $usuario)
                        <tr class="transition-colors hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-300">{{ $usuario->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $usuario->email }}</td>
                            <td class="px-4 py-3">
                                @php($ehAdmin = in_array($usuario->id, $adminsDoTenant, true))
                                @php($ehConvidado = in_array($usuario->id, $convidados, true))
                                <div class="flex flex-wrap gap-1">
                                    @if ($ehAdmin)
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-violet-500/15 text-violet-400">Admin</span>
                                    @endif
                                    @if ($ehConvidado)
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-400" title="Identidade de outra empresa: aqui só o vínculo é gerenciável">Convidado</span>
                                    @endif
                                    @foreach ($usuario->roles as $papel)
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-sky-500/15 text-sky-400">{{ $papel->name }}</span>
                                    @endforeach
                                    @if (! $ehAdmin && $usuario->roles->isEmpty())
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-slate-500/15 text-slate-300" title="Sem papel: só vê o que os vínculos por unidade liberam">Sem papel</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $usuario->status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                                    {{ ucfirst($usuario->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button wire:click="abrirVinculos({{ $usuario->id }})" class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-medium text-blue-400 hover:bg-slate-700 transition-colors">Vínculos</button>
                                @unless (in_array($usuario->id, $convidados, true))
                                    <button wire:click="abrirEditar({{ $usuario->id }})" class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700 transition-colors">Editar</button>
                                @endunless
                                <button wire:click="excluir({{ $usuario->id }})" wire:confirm="Confirma remover este usuário desta empresa?" class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-medium text-rose-400 hover:bg-slate-700 transition-colors">Excluir</button>
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
        <div class="border-t border-slate-800 px-4 py-3">
            {{ $usuarios->links() }}
        </div>
    </x-report-card>

    {{-- Fundação v0.5.0: vínculos sem alcance (pending_scope) não dão acesso até o admin definir --}}
    @if ($pendentes->isNotEmpty())
        <x-report-card padding="p-0" class="mt-4">
            <div class="border-b border-slate-800 px-4 py-3">
                <h3 class="text-sm font-semibold text-amber-300">Vínculos aguardando alcance</h3>
                <p class="text-xs text-slate-500">Estas pessoas estão ligadas a esta empresa, mas ainda sem acesso: defina o alcance para liberar. No Compras o alcance é corporativo; o acesso por unidade continua nos Vínculos de cada usuário.</p>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-800">
                    @foreach ($pendentes as $pendente)
                        <tr wire:key="pendente-{{ $pendente->id }}">
                            <td class="px-4 py-3 text-slate-300">{{ $pendente->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $pendente->email }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="definirAlcance({{ $pendente->id }})" wire:confirm="Liberar o acesso desta pessoa a esta empresa (alcance corporativo)?" class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-medium text-amber-300 hover:bg-slate-700 transition-colors">Definir alcance</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-report-card>
    @endif

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">{{ $editandoId ? 'Editar Usuário' : 'Convidar usuário' }}</h2>

                <div class="space-y-4">
                    @if ($editandoId)
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Nome</label>
                            <input type="text" wire:model="name" class="input-dark w-full @error('name') border-rose-500 @enderror">
                            @error('name') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">E-mail</label>
                        <input type="email" wire:model="email" class="input-dark w-full @error('email') border-rose-500 @enderror">
                        @error('email') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($editandoId)
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                            <select wire:model="status" class="input-dark w-full">
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                            @error('status') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" wire:model="isAdmin" class="rounded border-slate-700 bg-slate-800">
                        Administrador <span class="text-xs text-slate-500">— tudo, inclusive esta tela</span>
                    </label>

                    <div>
                        <p class="mb-1 text-sm font-medium text-slate-300">Papéis <span class="text-xs font-normal text-slate-500">— o que cada um pode fazer se ajusta em <a href="{{ route('admin.papeis') }}" wire:navigate class="underline hover:text-slate-300">Papéis &amp; Permissões</a></span></p>
                        @if ($papeisDisponiveis->isEmpty())
                            <p class="text-xs text-amber-300">Nenhum papel neste tenant — sincronize os papéis padrão em Papéis &amp; Permissões.</p>
                        @else
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                @foreach ($papeisDisponiveis as $papel)
                                    <label class="flex items-center gap-2 text-sm text-slate-300">
                                        <input type="checkbox" value="{{ $papel->id }}" wire:model="papeis" class="rounded border-slate-700 bg-slate-800">
                                        {{ $papel->name }}
                                        @unless ($papel->isCatalog())
                                            <span class="rounded bg-slate-700/60 px-1.5 text-[10px] text-slate-400">personalizado</span>
                                        @endunless
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('papeis.*') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    @if (! $editandoId)
                        <p class="text-xs text-slate-500">A pessoa recebe por e-mail um link de uso único (válido por tempo limitado) e cria a própria senha — ou entra na conta que já tem na suíte. Ninguém além dela conhece a senha.</p>
                    @endif
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="$set('mostrarModal', false)" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="salvar" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
                        {{ $editandoId ? 'Salvar' : 'Enviar convite' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Vínculos --}}
    @if ($mostrarModalVinculos && $usuarioVinculos)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-1">Vínculos — {{ $usuarioVinculos->name }}</h2>
                <p class="text-sm text-slate-500 mb-4">Gerencie as unidades e perfis deste usuário.</p>

                {{-- Lista de vínculos existentes --}}
                @forelse ($usuarioVinculos->unidades as $unidade)
                    <div class="flex items-center justify-between py-2 border-b border-slate-800">
                        <div>
                            <span class="text-sm font-medium text-slate-200">{{ $unidade->nome }}</span>
                            <span class="ml-2 text-xs text-slate-500">{{ \App\Enums\Perfil::tryFrom($unidade->pivot->perfil)?->label() ?? $unidade->pivot->perfil }} / {{ $unidade->pivot->nivel_alcada ?? '—' }}</span>
                        </div>
                        <button wire:click="removerVinculo({{ $unidade->id }})" class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1 text-xs font-medium text-rose-400 hover:bg-slate-700 transition-colors">Remover</button>
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
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @error('vincularPerfil') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror

                    <select wire:model="vincularNivelAlcada" class="input-dark w-full">
                        <option value="">Sem nível de alçada</option>
                        @foreach ($niveisAlcada as $n)
                            <option value="{{ $n->value }}">{{ ucfirst($n->value) }}</option>
                        @endforeach
                    </select>

                    <button wire:click="adicionarVinculo" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
                        Adicionar
                    </button>
                </div>

                <div class="flex justify-end mt-4">
                    <button wire:click="$set('mostrarModalVinculos', false)" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
