<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $pedido->numero }}</h1>
            <p class="text-gray-500 text-sm">Emitido em {{ $pedido->emitido_em?->format('d/m/Y H:i') }} por {{ $pedido->emissor?->name }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('compradora.pedidos.pdf', $pedido->id) }}" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Baixar PDF</a>
            @if($pedido->status->value === 'emitido')
                <button wire:click="$set('mostrarModalCancelar', true)" class="px-4 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">Cancelar</button>
            @endif
            <a href="{{ route('compradora.pedidos.index') }}" class="px-4 py-2 border rounded hover:bg-gray-50">Voltar</a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-white border rounded p-4">
            <p class="text-sm text-gray-500">Fornecedor</p>
            <p class="font-medium">{{ $pedido->fornecedor->razao_social }}</p>
            <p class="text-sm text-gray-500">CNPJ: {{ $pedido->fornecedor->cnpj }}</p>
        </div>
        <div class="bg-white border rounded p-4">
            <p class="text-sm text-gray-500">Unidade Requisitante</p>
            <p class="font-medium">{{ $pedido->unidade->nome }}</p>
        </div>
    </div>

    @if($pedido->condicoes_pagamento || $pedido->prazo_entrega || $pedido->modalidade_entrega)
    <div class="grid grid-cols-3 gap-4 mb-6">
        @if($pedido->condicoes_pagamento)
        <div class="bg-white border rounded p-4">
            <p class="text-sm text-gray-500 mb-1">Condições de Pagamento</p>
            <p class="text-sm">{{ $pedido->condicoes_pagamento }}</p>
        </div>
        @endif
        @if($pedido->prazo_entrega)
        <div class="bg-white border rounded p-4">
            <p class="text-sm text-gray-500 mb-1">Prazo de Entrega</p>
            <p class="text-sm font-medium">{{ $pedido->prazo_entrega->format('d/m/Y') }}</p>
        </div>
        @endif
        @if($pedido->modalidade_entrega)
        <div class="bg-white border rounded p-4">
            <p class="text-sm text-gray-500 mb-1">Modalidade de Entrega</p>
            <p class="text-sm font-medium">{{ $pedido->modalidade_entrega->label() }}</p>
        </div>
        @endif
    </div>
    @endif

    <h2 class="text-lg font-semibold mb-3">Itens</h2>
    <table class="w-full text-sm border-collapse mb-6">
        <thead>
            <tr class="bg-gray-50 text-left">
                <th class="p-2 border">Descrição</th>
                <th class="p-2 border">Requisição</th>
                <th class="p-2 border text-right">Qtd</th>
                <th class="p-2 border">Un</th>
                <th class="p-2 border text-right">Valor Unit.</th>
                <th class="p-2 border text-right">Total</th>
                <th class="p-2 border">Destino</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->itens as $item)
            <tr>
                <td class="p-2 border">{{ $item->descricao }}</td>
                <td class="p-2 border font-mono text-xs">{{ $item->requisicao->codigo }}</td>
                <td class="p-2 border text-right">{{ $item->quantidade }}</td>
                <td class="p-2 border">{{ $item->unidade_medida }}</td>
                <td class="p-2 border text-right font-mono">R$ {{ number_format((float)$item->valor_unitario, 2, ',', '.') }}</td>
                <td class="p-2 border text-right font-mono">R$ {{ number_format((float)$item->valor_total, 2, ',', '.') }}</td>
                <td class="p-2 border text-sm text-gray-600">{{ $item->destino ?? '—' }}</td>
            </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold">
                <td colspan="5" class="p-2 border text-right">Total</td>
                <td class="p-2 border text-right font-mono">R$ {{ number_format($pedido->itens->sum(fn($i) => (float)$i->valor_total), 2, ',', '.') }}</td>
                <td class="p-2 border"></td>
            </tr>
        </tbody>
    </table>

    @if($mostrarModalCancelar)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Cancelar Pedido de Compra</h3>
            <p class="text-sm text-gray-600 mb-4">Esta ação irá cancelar o pedido {{ $pedido->numero }} e retornar as requisições vinculadas para "Aprovada".</p>
            <textarea wire:model="motivoCancelamento" rows="3" placeholder="Motivo obrigatório para cancelar pedido emitido..." class="w-full border rounded p-2 text-sm mb-4"></textarea>
            @error('cancelamento') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('mostrarModalCancelar', false)" class="px-4 py-2 border rounded">Fechar</button>
                <button wire:click="cancelar" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Confirmar Cancelamento</button>
            </div>
        </div>
    </div>
    @endif
</div>
