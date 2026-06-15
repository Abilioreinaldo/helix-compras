<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Pedidos de Compra</h1>

    @error('acao')
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{{ $message }}</div>
    @enderror

    {{-- Sugestões de agrupamento --}}
    @if($sugestoes->isNotEmpty())
    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Sugestões de Agrupamento</h2>
        <div class="space-y-4">
            @foreach($sugestoes as $sugestao)
            <div class="border rounded-lg p-4 bg-white shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="font-medium">{{ $sugestao['fornecedor']->razao_social }}</span>
                        <span class="ml-2 text-sm text-gray-500">{{ $sugestao['requisicoes']->count() }} requisição(ões) — R$ {{ number_format($sugestao['valor_total'], 2, ',', '.') }}</span>
                    </div>
                    <button
                        wire:click="criarRascunho({{ $sugestao['fornecedor']->id }}, {{ json_encode($sugestao['requisicoes']->pluck('id')->toArray()) }})"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm"
                    >
                        Criar Rascunho
                    </button>
                </div>
                <div class="text-sm text-gray-600">
                    @foreach($sugestao['requisicoes'] as $req)
                        <span class="inline-block bg-gray-100 rounded px-2 py-0.5 mr-1">{{ $req->codigo }}</span>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Rascunhos --}}
    @if($rascunhos->isNotEmpty())
    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Rascunhos</h2>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-50 text-left">
                    <th class="p-2 border">Fornecedor</th>
                    <th class="p-2 border">Unidade</th>
                    <th class="p-2 border">Atualizado em</th>
                    <th class="p-2 border">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rascunhos as $rascunho)
                <tr class="hover:bg-gray-50">
                    <td class="p-2 border">{{ $rascunho->fornecedor->razao_social }}</td>
                    <td class="p-2 border">{{ $rascunho->unidade->nome }}</td>
                    <td class="p-2 border">{{ $rascunho->updated_at->format('d/m/Y H:i') }}</td>
                    <td class="p-2 border">
                        <a href="{{ route('compradora.pedidos.editar', $rascunho->id) }}" class="text-blue-600 hover:underline">Editar</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    @endif

    {{-- Emitidos --}}
    <section>
        <h2 class="text-lg font-semibold mb-3">Pedidos Emitidos</h2>
        @if($emitidos->isEmpty())
            <p class="text-gray-500">Nenhum pedido emitido.</p>
        @else
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-50 text-left">
                    <th class="p-2 border">Número</th>
                    <th class="p-2 border">Fornecedor</th>
                    <th class="p-2 border">Unidade</th>
                    <th class="p-2 border">Emitido em</th>
                    <th class="p-2 border">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($emitidos as $pedido)
                <tr class="hover:bg-gray-50">
                    <td class="p-2 border font-mono">{{ $pedido->numero }}</td>
                    <td class="p-2 border">{{ $pedido->fornecedor->razao_social }}</td>
                    <td class="p-2 border">{{ $pedido->unidade->nome }}</td>
                    <td class="p-2 border">{{ $pedido->emitido_em->format('d/m/Y H:i') }}</td>
                    <td class="p-2 border space-x-2">
                        <a href="{{ route('compradora.pedidos.detalhe', $pedido->id) }}" class="text-blue-600 hover:underline">Ver</a>
                        <a href="{{ route('compradora.pedidos.pdf', $pedido->id) }}" class="text-green-600 hover:underline">PDF</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $emitidos->links() }}
        @endif
    </section>
</div>
