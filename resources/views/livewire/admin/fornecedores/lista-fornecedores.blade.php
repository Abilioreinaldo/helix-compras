<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Fornecedores</h1>
        <button wire:click="abrirCriar" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Novo Fornecedor
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex gap-3 mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="busca"
            placeholder="Buscar por razão social ou CNPJ..."
            class="border border-gray-300 rounded-md px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
        <select wire:model.live="filtroAtivo" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos (ativo)</option>
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
        </select>
        <select wire:model.live="filtroHomologado" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos (homologado)</option>
            <option value="1">Homologado</option>
            <option value="0">Pendente</option>
        </select>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Razão Social</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CNPJ</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Homologado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($fornecedores as $fornecedor)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $fornecedor->razao_social }}
                            @if ($fornecedor->nome_fantasia)
                                <span class="text-xs text-gray-500 block">{{ $fornecedor->nome_fantasia }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $fornecedor->cnpj }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $fornecedor->categoria ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if ($fornecedor->homologado)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Sim</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Pendente</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $fornecedor->ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $fornecedor->ativo ? 'Sim' : 'Não' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            @unless ($fornecedor->homologado)
                                <button wire:click="homologar({{ $fornecedor->id }})" wire:confirm="Homologar este fornecedor?" class="text-green-600 hover:text-green-800">Homologar</button>
                            @endunless
                            <button wire:click="abrirEditar({{ $fornecedor->id }})" class="text-blue-600 hover:text-blue-800">Editar</button>
                            <button wire:click="excluir({{ $fornecedor->id }})" wire:confirm="Confirma exclusão?" class="text-red-600 hover:text-red-800">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Nenhum fornecedor encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $fornecedores->links() }}
        </div>
    </div>

    {{-- Modal Criar/Editar --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editandoId ? 'Editar Fornecedor' : 'Novo Fornecedor' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Razão Social</label>
                        <input type="text" wire:model="razaoSocial" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('razaoSocial') border-red-500 @enderror">
                        @error('razaoSocial') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome Fantasia</label>
                        <input type="text" wire:model="nomeFantasia" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ (14 dígitos)</label>
                        <input type="text" wire:model="cnpj" maxlength="14" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('cnpj') border-red-500 @enderror">
                        @error('cnpj') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <input type="text" wire:model="categoria" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contato — Nome</label>
                        <input type="text" wire:model="contatoNome" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contato — E-mail</label>
                        <input type="email" wire:model="contatoEmail" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('contatoEmail') border-red-500 @enderror">
                        @error('contatoEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contato — Telefone</label>
                        <input type="text" wire:model="contatoTelefone" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea wire:model="observacoes" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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
