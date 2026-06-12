<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Fila de Aprovações</h1>
        <p class="text-sm text-gray-500 mt-1">Requisições aguardando sua aprovação.</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aprovação iniciada</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($requisicoes as $req)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-700">
                            {{ $req->codigo ?? '—' }}
                            @if ($req->is_emergencial)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-800">Emergencial</span>
                            @endif
                            @if ($req->urgente)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-orange-100 text-orange-700">Urgente</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->solicitante?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->unidade?->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $req->aprovacao_iniciada_em?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('aprovacoes.painel', $req->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Revisar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Nenhuma aprovação pendente.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $requisicoes->links() }}
        </div>
    </div>
</div>
