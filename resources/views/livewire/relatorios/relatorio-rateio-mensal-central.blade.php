<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Rateio Mensal da Central</h1>
    </div>

    {{-- Filtros --}}
    <div class="flex gap-3 mb-4">
        <input type="number" min="1" max="12" wire:model.live="filtroMes" placeholder="Mês"
            class="w-28 border-gray-300 rounded shadow-sm text-sm" />
        <input type="number" min="2000" wire:model.live="filtroAno" placeholder="Ano"
            class="w-32 border-gray-300 rounded shadow-sm text-sm" />
    </div>

    <div class="bg-white border rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor da Central</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unidades</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($rateios as $rateio)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">{{ str_pad($rateio->mes, 2, '0', STR_PAD_LEFT) }}/{{ $rateio->ano }}</td>
                        <td class="px-4 py-3 text-sm text-right">R$ {{ number_format($rateio->valor_total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ $rateio->unidades->count() }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="toggleExpandir({{ $rateio->id }})" class="text-blue-600 hover:text-blue-800 text-sm">
                                {{ $expandidoId === $rateio->id ? 'Recolher' : 'Detalhar' }}
                            </button>
                        </td>
                    </tr>

                    @if($expandidoId === $rateio->id)
                        <tr>
                            <td colspan="4" class="px-4 py-3 bg-gray-50">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-xs text-gray-500 uppercase">
                                            <th class="px-3 py-2 text-left">Unidade</th>
                                            <th class="px-3 py-2 text-right">% Consumo</th>
                                            <th class="px-3 py-2 text-right">Valor Rateado</th>
                                            <th class="px-3 py-2 text-center">Status</th>
                                            @if($ehAdmin)<th class="px-3 py-2"></th>@endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rateio->unidades as $linha)
                                            @php
                                                $revertido = $linha->movimentacoes->contains('tipo', \App\Enums\TipoMovimentacao::DescontoRateio);
                                            @endphp
                                            <tr class="border-t border-gray-200">
                                                <td class="px-3 py-2">{{ $linha->unidade->nome ?? '—' }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format((float) $linha->percentual_consumo * 100, 2, ',', '.') }}%</td>
                                                <td class="px-3 py-2 text-right">R$ {{ number_format($linha->valor_rateado, 2, ',', '.') }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    @if($revertido)
                                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700">Revertido</span>
                                                    @else
                                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                                                    @endif
                                                </td>
                                                @if($ehAdmin)
                                                    <td class="px-3 py-2 text-right">
                                                        @if(! $revertido && (float) $linha->valor_rateado > 0)
                                                            <button wire:click="abrirReversao({{ $linha->id }})" class="text-red-600 hover:text-red-800 text-xs">Reverter</button>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Nenhum rateio encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de reversão --}}
    @if($revertendoItemId !== null)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold mb-4">Reverter Rateio</h2>
                <p class="text-sm text-gray-600 mb-3">Será gerado um crédito (Desconto de Rateio) no valor rateado desta unidade.</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <textarea wire:model="motivoReversao" rows="3" class="w-full border-gray-300 rounded shadow-sm text-sm" placeholder="Informe o motivo da reversão"></textarea>
                @error('motivoReversao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3 mt-5">
                    <button wire:click="cancelarReversao" class="px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50">Cancelar</button>
                    <button wire:click="confirmarReversao" class="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700">Confirmar reversão</button>
                </div>
            </div>
        </div>
    @endif
</div>
