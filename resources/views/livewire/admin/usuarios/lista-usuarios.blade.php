<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Usuários</h1>
        <button wire:click="abrirCriar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Novo Usuário
        </button>
    </div>

    {{-- Filtro --}}
    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="busca"
            placeholder="Buscar por nome ou e-mail..."
            class="border border-gray-300 rounded-md px-3 py-2 text-sm w-full max-w-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">E-mail</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perfil</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($usuarios as $usuario)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $usuario->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $usuario->email }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            @if ($usuario->is_admin) <span class="inline-flex px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800">Admin</span> @endif
                            @if ($usuario->is_compradora) <span class="inline-flex px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">Compradora</span> @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $usuario->status === 'ativo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($usuario->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            <button wire:click="abrirVinculos({{ $usuario->id }})" class="text-green-600 hover:text-green-800">Vínculos</button>
                            <button wire:click="abrirEditar({{ $usuario->id }})" class="text-blue-600 hover:text-blue-800">Editar</button>
                            <button wire:click="excluir({{ $usuario->id }})" wire:confirm="Confirma exclusão?" class="text-red-600 hover:text-red-800">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Nenhum usuário encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $usuarios->links() }}
        </div>
    </div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editandoId ? 'Editar Usuário' : 'Novo Usuário' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input type="text" wire:model="name" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                        <input type="email" wire:model="email" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model="status" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>

                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isAdmin" class="rounded border-gray-300">
                            Administrador
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isCompradora" class="rounded border-gray-300">
                            Compradora
                        </label>
                    </div>

                    @if (! $editandoId)
                        <p class="text-xs text-gray-500">Uma senha provisória será gerada automaticamente e o usuário deverá trocá-la no primeiro acesso.</p>
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

    {{-- Modal Vínculos --}}
    @if ($mostrarModalVinculos && $usuarioVinculos)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-1">Vínculos — {{ $usuarioVinculos->name }}</h2>
                <p class="text-sm text-gray-500 mb-4">Gerencie as unidades e perfis deste usuário.</p>

                {{-- Lista de vínculos existentes --}}
                @forelse ($usuarioVinculos->unidades as $unidade)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <div>
                            <span class="text-sm font-medium text-gray-800">{{ $unidade->nome }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ $unidade->pivot->perfil }} / {{ $unidade->pivot->nivel_alcada ?? '—' }}</span>
                        </div>
                        <button wire:click="removerVinculo({{ $unidade->id }})" class="text-red-600 hover:text-red-800 text-xs">Remover</button>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 mb-4">Nenhum vínculo cadastrado.</p>
                @endforelse

                {{-- Adicionar novo vínculo --}}
                <div class="mt-4 space-y-3">
                    <p class="text-sm font-semibold text-gray-700">Adicionar vínculo</p>
                    <select wire:model="vincularUnidadeId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('vincularUnidadeId') border-red-500 @enderror">
                        <option value="">Selecione a unidade...</option>
                        @foreach ($todasUnidades as $u)
                            <option value="{{ $u->id }}">{{ $u->nome }}</option>
                        @endforeach
                    </select>
                    @error('vincularUnidadeId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <select wire:model="vincularPerfil" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('vincularPerfil') border-red-500 @enderror">
                        <option value="">Selecione o perfil...</option>
                        @foreach ($perfis as $p)
                            <option value="{{ $p->value }}">{{ $p->value }}</option>
                        @endforeach
                    </select>
                    @error('vincularPerfil') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <select wire:model="vincularNivelAlcada" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Sem nível de alçada</option>
                        @foreach ($niveisAlcada as $n)
                            <option value="{{ $n->value }}">{{ ucfirst($n->value) }}</option>
                        @endforeach
                    </select>

                    <button wire:click="adicionarVinculo" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Adicionar
                    </button>
                </div>

                <div class="flex justify-end mt-4">
                    <button wire:click="$set('mostrarModalVinculos', false)" class="text-sm text-gray-600 hover:text-gray-800 px-4 py-2 border border-gray-300 rounded-md">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
