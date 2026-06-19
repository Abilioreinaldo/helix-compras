<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Atendimento de Requisições de Material</h1>

    @if($erroAtendimento)
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 text-sm">
            {{ $erroAtendimento }}
        </div>
    @endif

    @if($recusandoId)
        <div class="bg-white border rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Recusar Requisição #{{ $recusandoId }}</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo da Recusa</label>
                    <textarea
                        wire:model="motivoRecusa"
                        rows="3"
                        class="w-full border-gray-300 rounded shadow-sm text-sm"
                        placeholder="Descreva o motivo da recusa..."
                    ></textarea>
                    @error('motivoRecusa') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3">
                    <button wire:click="confirmarRecusa" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">
                        Confirmar Recusa
                    </button>
                    <button wire:click="cancelarRecusa" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($requisicoes->isEmpty())
        <p class="text-gray-500">Nenhuma requisição de material pendente.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd Solicitada</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Disponível</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Justificativa</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($requisicoes as $req)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $req->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $req->solicitante?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ $req->saldoEstoque?->descricao_item ?? '—' }}
                                @if ($saldoIdsVencidos->contains($req->saldo_estoque_id))
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800" title="A saída debitará lote vencido">⚠️ Vencido</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format($req->quantidade_solicitada, 3, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-right {{ (float)$req->saldoEstoque?->quantidade < (float)$req->quantidade_solicitada ? 'text-red-600 font-medium' : '' }}">
                                {{ number_format($req->saldoEstoque?->quantidade ?? 0, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $req->justificativa }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex gap-2">
                                    <button
                                        wire:click="atender({{ $req->id }})"
                                        wire:confirm="Confirma o atendimento da requisição #{{ $req->id }}?"
                                        class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-xs"
                                    >
                                        Atender
                                    </button>
                                    <button
                                        wire:click="abrirRecusa({{ $req->id }})"
                                        class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-xs"
                                    >
                                        Recusar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $requisicoes->links() }}</div>
    @endif
</div>
