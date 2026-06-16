<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Requisições de Material</h1>
        <button
            wire:click="abrirFormulario"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"
        >
            Nova Requisição
        </button>
    </div>

    @if($mostrarFormulario)
        <div class="bg-white border rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Abrir Nova Requisição</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item do Estoque</label>
                    <select wire:model="saldoEstoqueId" class="w-full border-gray-300 rounded shadow-sm text-sm">
                        <option value="">Selecione o item...</option>
                        @foreach($saldosDisponiveis as $saldo)
                            <option value="{{ $saldo->id }}">
                                {{ $saldo->descricao_item }} — {{ $saldo->deposito }} (Saldo: {{ number_format($saldo->quantidade, 3, ',', '.') }} {{ $saldo->unidade_medida }})
                            </option>
                        @endforeach
                    </select>
                    @error('saldoEstoqueId') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade Solicitada</label>
                    <input
                        wire:model="quantidadeSolicitada"
                        type="number"
                        step="0.001"
                        min="0.001"
                        class="w-full border-gray-300 rounded shadow-sm text-sm"
                        placeholder="0.000"
                    />
                    @error('quantidadeSolicitada') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa</label>
                    <textarea
                        wire:model="justificativa"
                        rows="3"
                        class="w-full border-gray-300 rounded shadow-sm text-sm"
                        placeholder="Descreva o motivo da requisição..."
                    ></textarea>
                    @error('justificativa') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3">
                    <button wire:click="salvar" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                        Abrir Requisição
                    </button>
                    <button wire:click="fecharFormulario" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($requisicoes->isEmpty())
        <p class="text-gray-500">Você ainda não possui requisições de material.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantidade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Justificativa / Motivo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($requisicoes as $req)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $req->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $req->saldoEstoque?->descricao_item ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format($req->quantidade_solicitada, 3, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $cor = match($req->status) {
                                        \App\Enums\StatusRequisicaoMaterial::Aberta => 'bg-yellow-100 text-yellow-800',
                                        \App\Enums\StatusRequisicaoMaterial::Atendida => 'bg-green-100 text-green-800',
                                        \App\Enums\StatusRequisicaoMaterial::Recusada => 'bg-red-100 text-red-800',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $cor }}">{{ $req->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $req->justificativa }}
                                @if($req->motivo_recusa)
                                    <p class="text-red-600 text-xs mt-1">Recusa: {{ $req->motivo_recusa }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $requisicoes->links() }}</div>
    @endif
</div>
