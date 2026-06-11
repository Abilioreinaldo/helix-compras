<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Minhas Requisições</h1>
        <a href="{{ route('requisicoes.criar') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
            Nova Requisição
        </a>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <select wire:model.live="filtroStatus" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos os status</option>
            @foreach ($statusDisponiveis as $s)
                <option value="{{ $s->value }}">{{ ucwords(str_replace('_', ' ', $s->value)) }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" wire:model.live="filtroUrgente" class="rounded">
            Somente urgentes
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" wire:model.live="filtroAtrasada" class="rounded">
            Somente atrasadas
        </label>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criada em</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($requisicoes as $req)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-700">
                            {{ $req->codigo ?? '(rascunho)' }}
                            @if ($req->atrasada)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700">Atrasada</span>
                            @endif
                            @if ($req->urgente)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-orange-100 text-orange-700">Urgente</span>
                            @endif
                            @if ($req->is_emergencial)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-800">Emergencial</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->itens->count() }} {{ Str::plural('item', $req->itens->count()) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->unidade->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->solicitante->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                {{ ucwords(str_replace('_', ' ', $req->status->value)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('requisicoes.detalhe', $req->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">Nenhuma requisição encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $requisicoes->links() }}
        </div>
    </div>
</div>
