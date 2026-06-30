<div class="report-canvas">
    @php
        $badgeClass = match($pedido->status->value) {
            'emitido'   => 'bg-emerald-500/15 text-emerald-400',
            'cancelado' => 'bg-rose-500/15 text-rose-400',
            default     => 'bg-slate-500/15 text-slate-300',
        };
    @endphp

    <x-page-header
        title="{{ $pedido->numero }}"
        icon="cart"
        subtitle="Emitido em {{ $pedido->emitido_em?->format('d/m/Y H:i') }} por {{ $pedido->emissor?->name }}"
    >
        <x-slot:actions>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass }}">
                {{ ucfirst($pedido->status->value) }}
            </span>
            <a href="{{ route('compradora.pedidos.pdf', $pedido->id) }}"
               class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition-colors">
                Baixar PDF
            </a>
            @if($pedido->status->value === 'emitido')
                <button wire:click="$set('mostrarModalCancelar', true)"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                    Cancelar
                </button>
            @endif
            <a href="{{ route('compradora.pedidos.index') }}"
               class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition-colors">
                Voltar
            </a>
        </x-slot:actions>
    </x-page-header>

    @error('cancelamento')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-6">
        <x-report-card title="Fornecedor">
            <p class="text-base font-medium text-slate-100">{{ $pedido->fornecedor->razao_social }}</p>
            <p class="mt-1 text-sm text-slate-400">CNPJ: {{ $pedido->fornecedor->cnpj }}</p>
        </x-report-card>

        <x-report-card title="Unidade Requisitante">
            <p class="text-base font-medium text-slate-100">{{ $pedido->unidade->nome }}</p>
        </x-report-card>
    </div>

    @if($pedido->condicoes_pagamento || $pedido->prazo_entrega || $pedido->modalidade_entrega)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
            @if($pedido->condicoes_pagamento)
                <x-report-card title="Condições de Pagamento">
                    <p class="text-sm text-slate-300">{{ $pedido->condicoes_pagamento }}</p>
                </x-report-card>
            @endif
            @if($pedido->prazo_entrega)
                <x-report-card title="Prazo de Entrega">
                    <p class="text-sm font-medium text-slate-200">{{ $pedido->prazo_entrega->format('d/m/Y') }}</p>
                </x-report-card>
            @endif
            @if($pedido->modalidade_entrega)
                <x-report-card title="Modalidade de Entrega">
                    <p class="text-sm font-medium text-slate-200">{{ $pedido->modalidade_entrega->label() }}</p>
                </x-report-card>
            @endif
        </div>
    @endif

    <x-report-card title="Itens" padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Requisição</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Un</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor Unit.</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Destino</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($pedido->itens as $item)
                        <tr class="transition-colors hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-300">{{ $item->descricao }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-300">{{ $item->requisicao->codigo }}</td>
                            <td class="px-4 py-3 text-right text-slate-300">{{ $item->quantidade }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $item->unidade_medida }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-300">R$ {{ number_format((float)$item->valor_unitario, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-300">R$ {{ number_format((float)$item->valor_total, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $item->destino ?? '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-950/40">
                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-slate-100">Total</td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-slate-100">R$ {{ number_format($pedido->itens->sum(fn($i) => (float)$i->valor_total), 2, ',', '.') }}</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-report-card>

    @if($mostrarModalCancelar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-6 shadow-xl">
                <h3 class="mb-4 text-base font-semibold text-slate-100">Cancelar Pedido de Compra</h3>
                <p class="mb-4 text-sm text-slate-400">Esta ação irá cancelar o pedido {{ $pedido->numero }} e retornar as requisições vinculadas para "Aprovada".</p>
                <textarea wire:model="motivoCancelamento"
                          rows="3"
                          placeholder="Motivo obrigatório para cancelar pedido emitido..."
                          class="mb-4 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-600"></textarea>
                @error('cancelamento')
                    <p class="mb-2 text-sm text-rose-400">{{ $message }}</p>
                @enderror
                <div class="flex justify-end gap-2">
                    <button wire:click="$set('mostrarModalCancelar', false)"
                            class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition-colors">
                        Fechar
                    </button>
                    <button wire:click="cancelar"
                            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                        Confirmar Cancelamento
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
