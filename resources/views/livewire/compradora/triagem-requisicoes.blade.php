<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Fila de Triagem</h1>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens / Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submetida</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($requisicoes as $req)
                    <tr class="hover:bg-gray-50 {{ $req->atrasada ? 'bg-red-50' : '' }}">
                        <td class="px-4 py-3 text-sm font-mono text-gray-700">
                            {{ $req->codigo }}
                            @if ($req->atrasada) <span class="ml-1 text-xs text-red-600 font-semibold">⚠ Atrasada</span> @endif
                            @if ($req->is_emergencial) <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700">Emergencial</span> @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $req->solicitante->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->unidade->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $req->itens->count() }} iten(s)
                            @if ($req->valorTotal() > 0)
                                <span class="block text-xs text-gray-500">R$ {{ number_format($req->valorTotal(), 2, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                {{ ucwords(str_replace('_', ' ', $req->status->value)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $req->submetida_em?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            <a href="{{ route('requisicoes.detalhe', $req->id) }}" class="text-gray-600 hover:text-gray-800">Ver</a>
                            @if ($req->status->value === 'aguardando_triagem')
                                <button wire:click="iniciarTriagem({{ $req->id }})" class="text-blue-600 hover:text-blue-800">Iniciar</button>
                            @endif
                            @if ($req->status->value === 'em_triagem')
                                <button wire:click="enviarParaCotacao({{ $req->id }})" class="text-green-600 hover:text-green-800">Cotação</button>
                                <button wire:click="abrirDevolucao({{ $req->id }})" class="text-orange-600 hover:text-orange-800">Devolver</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">Nenhuma requisição aguardando triagem.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $requisicoes->links() }}
        </div>
    </div>

    {{-- Modal devolução --}}
    @if ($devolvendo)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Devolver ao Solicitante</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-red-500">*</span></label>
                    <textarea wire:model="observacaoDevolucao" rows="3"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('observacaoDevolucao') border-red-500 @enderror"
                        placeholder="Informe o que precisa ser ajustado..."></textarea>
                    @error('observacaoDevolucao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button wire:click="$set('devolvendo', null)" class="text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="confirmarDevolucao" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Devolver
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
