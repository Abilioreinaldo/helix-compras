<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Pedidos para Recebimento</h1>

    @if(session('sucesso'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">{{ session('sucesso') }}</div>
    @endif

    @if($pedidos->isEmpty())
        <p class="text-gray-500">Nenhum pedido de compra emitido aguardando recebimento na sua unidade.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emitido em</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recebimento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($pedidos as $pedido)
                        @php $statusRec = $pedido->statusRecebimento(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-sm">{{ $pedido->numero }}</td>
                            <td class="px-4 py-3 text-sm">{{ $pedido->fornecedor->razao_social }}</td>
                            <td class="px-4 py-3 text-sm">{{ $pedido->unidade->nome }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $pedido->emitido_em?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($statusRec->value === 'total')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">{{ $statusRec->label() }}</span>
                                @elseif($statusRec->value === 'parcial')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">{{ $statusRec->label() }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $statusRec->label() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($statusRec->value !== 'total')
                                    <a href="{{ route('almoxarife.recebimentos.registrar', $pedido->id) }}" class="text-sm text-blue-600 hover:underline">Registrar</a>
                                @else
                                    <span class="text-sm text-gray-400">Concluído</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $pedidos->links() }}</div>
    @endif
</div>
