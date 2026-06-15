<div class="p-6">
    <h1 class="text-2xl font-bold mb-2">Requisições Pendentes por Aprovador</h1>
    <p class="text-sm text-gray-500 mb-6">Snapshot atual — apenas aprovações do ciclo vigente de cada requisição.</p>

    @if($resultados->isEmpty())
        <p class="text-gray-500">Nenhuma aprovação pendente no momento.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aprovador</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pendentes</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mais Antiga</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($resultados as $linha)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium">{{ $linha->aprovador_nome }}</td>
                            <td class="px-4 py-3 text-sm text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $linha->total_pendentes >= 5 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $linha->total_pendentes }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">
                                {{ $linha->mais_antiga ? \Carbon\Carbon::parse($linha->mais_antiga)->format('d/m/Y') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr>
                        <td class="px-4 py-3 text-sm font-semibold">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-bold">{{ $resultados->sum('total_pendentes') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
