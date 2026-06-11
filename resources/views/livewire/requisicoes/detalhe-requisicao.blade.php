<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $requisicao->codigo ?? 'Rascunho' }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Criada em {{ $requisicao->created_at->format('d/m/Y H:i') }} por {{ $requisicao->solicitante->name ?? '—' }}</p>
        </div>
        <div class="flex gap-2">
            @if ($requisicao->status->value === 'em_cotacao' && auth()->user()->temPerfil(\App\Enums\Perfil::CompradoraSenior))
                <a href="{{ route('compradora.cotacoes', $requisicao->id) }}"
                    class="text-sm text-blue-600 hover:text-blue-800 border border-blue-300 px-3 py-1.5 rounded-md">
                    Gerenciar Cotações
                </a>
            @endif
            @if ($requisicao->status->permiteEdicao())
                <a href="{{ route('requisicoes.editar', $requisicao->id) }}"
                    class="text-sm text-blue-600 hover:text-blue-800 border border-blue-300 px-3 py-1.5 rounded-md">
                    Editar
                </a>
            @endif
            @if (! $requisicao->status->ehTerminal())
                <button wire:click="abrirModalCancelar"
                    class="text-sm text-red-600 hover:text-red-800 border border-red-300 px-3 py-1.5 rounded-md">
                    Cancelar
                </button>
            @endif
        </div>
    </div>

    {{-- Badges --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
            {{ ucwords(str_replace('_', ' ', $requisicao->status->value)) }}
        </span>
        @if ($requisicao->urgente)
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Urgente</span>
        @endif
        @if ($requisicao->is_emergencial)
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Emergencial</span>
        @endif
        @if ($requisicao->atrasada)
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Atrasada</span>
        @endif
        @if ($requisicao->escalada_verba)
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Escalada (verba)</span>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Dados gerais --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Dados Gerais</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Unidade</dt>
                    <dd class="text-gray-800">{{ $requisicao->unidade->nome ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Centro de Custo</dt>
                    <dd class="text-gray-800">{{ $requisicao->centroCusto->nome ?? '—' }}</dd>
                </div>
                @if ($requisicao->obra)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Obra</dt>
                        <dd class="text-gray-800">{{ $requisicao->obra->id }}</dd>
                    </div>
                    @if ($requisicao->consumo_verba_no_submit)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Consumo verba (submit)</dt>
                            <dd class="text-gray-800">R$ {{ number_format($requisicao->consumo_verba_no_submit, 2, ',', '.') }}</dd>
                        </div>
                    @endif
                @endif
                @if ($requisicao->faixaAlcada)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Alçada</dt>
                        <dd class="text-gray-800">{{ $requisicao->faixaAlcada->nome }}</dd>
                    </div>
                @endif
                @if ($requisicao->justificativa)
                    <div>
                        <dt class="text-gray-500 mb-1">Justificativa</dt>
                        <dd class="text-gray-800 bg-gray-50 p-2 rounded text-xs">{{ $requisicao->justificativa }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Itens --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Itens</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500">
                        <th class="pb-2">Descrição</th>
                        <th class="pb-2 text-right">Qtd</th>
                        <th class="pb-2 text-right">Valor unit.</th>
                        <th class="pb-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($requisicao->itens as $item)
                        <tr>
                            <td class="py-1.5 text-gray-800">{{ $item->descricao }}</td>
                            <td class="py-1.5 text-right text-gray-600">{{ $item->quantidade }} {{ $item->unidade_medida }}</td>
                            <td class="py-1.5 text-right text-gray-600">
                                {{ $item->valor_unitario_estimado ? 'R$ '.number_format($item->valor_unitario_estimado, 2, ',', '.') : '—' }}
                            </td>
                            <td class="py-1.5 text-right text-gray-800 font-medium">
                                @if ($item->valor_unitario_estimado)
                                    R$ {{ number_format($item->quantidade * $item->valor_unitario_estimado, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200">
                        <td colspan="3" class="pt-2 text-right text-sm font-medium text-gray-700">Total estimado</td>
                        <td class="pt-2 text-right text-sm font-bold text-gray-800">
                            R$ {{ number_format($requisicao->valorTotal(), 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Histórico de status --}}
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Histórico</h2>
        <ol class="relative border-l border-gray-200 ml-3 space-y-4">
            @foreach ($requisicao->logs->sortBy('created_at') as $log)
                <li class="ml-4">
                    <div class="absolute w-2 h-2 bg-gray-400 rounded-full -left-1 mt-1.5"></div>
                    <p class="text-sm text-gray-800">
                        <span class="font-medium">{{ ucwords(str_replace('_', ' ', $log->status_novo)) }}</span>
                        @if ($log->status_anterior)
                            <span class="text-gray-400"> ← {{ ucwords(str_replace('_', ' ', $log->status_anterior->value)) }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ $log->created_at->format('d/m/Y H:i') }}
                        @if ($log->usuario) · {{ $log->usuario->name }} @endif
                        @if ($log->automatico) · <em>automático</em> @endif
                    </p>
                    @if ($log->observacao)
                        <p class="text-xs text-gray-600 mt-0.5 italic">{{ $log->observacao }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Modal cancelar --}}
    @if ($mostrarModalCancelar)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Cancelar Requisição</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-red-500">*</span></label>
                    <textarea wire:model="motivoCancelamento" rows="3"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('motivoCancelamento') border-red-500 @enderror"></textarea>
                    @error('motivoCancelamento') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button wire:click="$set('mostrarModalCancelar', false)" class="text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50">
                        Voltar
                    </button>
                    <button wire:click="cancelarRequisicao" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
