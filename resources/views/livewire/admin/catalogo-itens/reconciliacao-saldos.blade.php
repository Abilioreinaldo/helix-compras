<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Reconciliação de Saldos</h1>
    </div>

    <p class="text-sm text-gray-600 mb-4">
        Saldos de estoque ainda não vinculados a um item do catálogo. Confirme a sugestão sugerida ou busque manualmente o item correto.
    </p>

    @error('vinculo')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depósito</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sugestões</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($saldos as $saldo)
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $saldo->descricao_item }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $saldo->unidade->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $saldo->deposito }}</td>
                        <td class="px-4 py-3 text-sm">
                            @forelse ($sugestoes[$saldo->id] ?? [] as $sugestao)
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                        {{ $sugestao['confianca'] === 'alta' ? 'bg-green-100 text-green-800' : ($sugestao['confianca'] === 'media' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600') }}">
                                        {{ ucfirst($sugestao['confianca']) }} ({{ number_format($sugestao['score'] * 100, 0) }}%)
                                    </span>
                                    <span class="text-gray-700">{{ $sugestao['item']->descricao }}</span>
                                    <button wire:click="vincular({{ $saldo->id }}, {{ $sugestao['item']->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Confirmar</button>
                                </div>
                            @empty
                                <span class="text-xs text-gray-400">Nenhuma sugestão encontrada.</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <button wire:click="abrirVinculoManual({{ $saldo->id }})" class="text-blue-600 hover:text-blue-800">Buscar manualmente</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Nenhum saldo pendente de reconciliação.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $saldos->links() }}
        </div>
    </div>

    {{-- Modal de busca manual --}}
    @if ($saldoSelecionadoId)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Buscar Item de Catálogo</h2>

                <input type="text" wire:model.live.debounce.300ms="buscaManual" placeholder="Buscar por descrição ou código..."
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500">

                <div class="space-y-2 max-h-80 overflow-y-auto">
                    @forelse ($itensBuscaManual as $item)
                        <div class="flex items-center justify-between border border-gray-200 rounded-md px-3 py-2">
                            <span class="text-sm text-gray-700">{{ $item->codigo ? $item->codigo.' — ' : '' }}{{ $item->descricao }}</span>
                            <button wire:click="vincular({{ $saldoSelecionadoId }}, {{ $item->id }})" class="text-blue-600 hover:text-blue-800 text-sm">Vincular</button>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Digite para buscar itens do catálogo.</p>
                    @endforelse
                </div>

                <div class="flex justify-end mt-4">
                    <button wire:click="fecharVinculoManual" class="text-sm text-gray-600 hover:text-gray-800 px-4 py-2 border border-gray-300 rounded-md">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
