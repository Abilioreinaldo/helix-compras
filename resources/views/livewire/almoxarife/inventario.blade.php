<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Inventário</h1>
        @if(!$sessaoAtiva && !$mostrarFormAbrir)
            <button wire:click="abrirFormAbrir" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                Iniciar Inventário
            </button>
        @endif
    </div>

    @if($erro)
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 text-sm">
            {{ $erro }}
        </div>
    @endif

    @if($mostrarFormAbrir)
        <div class="bg-white border rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Abrir Nova Sessão de Inventário</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Depósito (deixe em branco para inventário da unidade inteira)
                    </label>
                    <input
                        wire:model="depositoAbertura"
                        type="text"
                        class="w-full border-gray-300 rounded shadow-sm text-sm"
                        placeholder="Ex: Depósito Central (opcional)"
                    />
                </div>
                <div class="flex gap-3">
                    <button wire:click="abrirSessao" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                        Abrir Sessão
                    </button>
                    <button wire:click="fecharFormAbrir" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($sessaoAtiva)
        <div class="bg-white border rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">
                    Sessão #{{ $sessaoAtiva->id }}
                    @if($sessaoAtiva->deposito)
                        — {{ $sessaoAtiva->deposito }}
                    @else
                        — Unidade Inteira
                    @endif
                </h2>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                    {{ $sessaoAtiva->status->label() }}
                </span>
            </div>

            @if($mostrarModalAplicar)
                <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
                    <h3 class="text-sm font-semibold text-blue-800 mb-3">Aplicar Inventário</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa</label>
                            <textarea
                                wire:model="justificativaAplicar"
                                rows="2"
                                class="w-full border-gray-300 rounded shadow-sm text-sm"
                                placeholder="Descreva o motivo do inventário..."
                            ></textarea>
                        </div>
                        <div class="flex gap-3">
                            <button wire:click="aplicar" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">
                                Confirmar Aplicação
                            </button>
                            <button wire:click="fecharModalAplicar" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-x-auto mb-4">
                <table class="min-w-full border rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd Sistema</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd Contada</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Divergência</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($sessaoAtiva->itens as $item)
                            <tr>
                                <td class="px-4 py-3 text-sm">{{ $item->saldoEstoque?->descricao_item ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-right">{{ number_format($item->quantidade_sistema, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <input
                                        wire:model="quantidadesContadas.{{ $item->id }}"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        class="w-32 border-gray-300 rounded shadow-sm text-sm text-right"
                                        placeholder="0.000"
                                    />
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    @if(isset($quantidadesContadas[$item->id]) && is_numeric($quantidadesContadas[$item->id]))
                                        @php $div = (float)$quantidadesContadas[$item->id] - (float)$item->quantidade_sistema; @endphp
                                        <span class="{{ $div > 0 ? 'text-green-600' : ($div < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                            {{ ($div >= 0 ? '+' : '') . number_format($div, 3, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex gap-3">
                <button wire:click="abrirModalAplicar" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">
                    Aplicar Inventário
                </button>
                <button
                    wire:click="cancelar"
                    wire:confirm="Confirma o cancelamento desta sessão de inventário?"
                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm"
                >
                    Cancelar Sessão
                </button>
            </div>
        </div>
    @endif

    @if($historico->isNotEmpty())
        <div class="bg-white border rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Histórico Recente</h2>
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depósito</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($historico as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $s->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $s->unidade?->nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $s->deposito ?? 'Unidade Inteira' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $cor = match($s->status) {
                                        \App\Enums\StatusInventario::Concluido => 'bg-green-100 text-green-800',
                                        \App\Enums\StatusInventario::Cancelado => 'bg-red-100 text-red-800',
                                        default => 'bg-yellow-100 text-yellow-800',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $cor }}">{{ $s->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
