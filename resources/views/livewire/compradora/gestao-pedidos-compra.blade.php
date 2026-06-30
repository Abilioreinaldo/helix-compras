<div class="report-canvas">
    <x-page-header title="Pedidos de Compra" icon="cart" subtitle="Gerencie sugestões de agrupamento, rascunhos e pedidos emitidos." />

    @error('acao')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    {{-- Sugestões de agrupamento --}}
    @if($sugestoes->isNotEmpty())
        <x-report-card title="Sugestões de Agrupamento" icon="light-bulb" padding="p-0">
            <div class="space-y-0 divide-y divide-slate-800">
                @foreach($sugestoes as $sugestao)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div>
                            <span class="font-medium text-slate-200">{{ $sugestao['fornecedor']->razao_social }}</span>
                            <span class="ml-2 text-sm text-slate-400">{{ $sugestao['requisicoes']->count() }} requisição(ões) — R$ {{ number_format($sugestao['valor_total'], 2, ',', '.') }}</span>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach($sugestao['requisicoes'] as $req)
                                    <span class="inline-block rounded bg-slate-800 px-2 py-0.5 text-xs text-slate-300">{{ $req->codigo }}</span>
                                @endforeach
                            </div>
                        </div>
                        <button
                            wire:click="criarRascunho({{ $sugestao['fornecedor']->id }}, {{ json_encode($sugestao['requisicoes']->pluck('id')->toArray()) }})"
                            wire:loading.attr="disabled"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500"
                        >
                            Criar Rascunho
                        </button>
                    </div>
                @endforeach
            </div>
        </x-report-card>
    @endif

    {{-- Rascunhos --}}
    @if($rascunhos->isNotEmpty())
        <x-report-card title="Rascunhos" icon="pencil-square" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Atualizado em</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($rascunhos as $rascunho)
                            <tr class="transition-colors hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-slate-200">{{ $rascunho->fornecedor->razao_social }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $rascunho->unidade->nome }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $rascunho->updated_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('compradora.pedidos.editar', $rascunho->id) }}" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 border border-slate-700 hover:bg-slate-700">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>
    @endif

    {{-- Emitidos --}}
    <x-report-card title="Pedidos Emitidos" icon="check-circle" padding="p-0">
        @if($emitidos->isEmpty())
            <x-empty-state
                icon="check-circle"
                title="Nenhum pedido emitido"
                message="Nenhum pedido emitido."
            />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Número</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Emitido em</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($emitidos as $pedido)
                            <tr class="transition-colors hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono text-slate-300">{{ $pedido->numero }}</td>
                                <td class="px-4 py-3 text-slate-200">{{ $pedido->fornecedor->razao_social }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $pedido->unidade->nome }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $pedido->emitido_em->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('compradora.pedidos.detalhe', $pedido->id) }}" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 border border-slate-700 hover:bg-slate-700">Ver</a>
                                        <a href="{{ route('compradora.pedidos.pdf', $pedido->id) }}" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 border border-slate-700 hover:bg-slate-700">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-4 py-3">
                {{ $emitidos->links() }}
            </div>
        @endif
    </x-report-card>
</div>
