<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $requisicao->codigo ?? '—' }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Solicitada por {{ $requisicao->solicitante?->name ?? '—' }} &mdash; {{ $requisicao->unidade?->nome ?? '—' }}
            </p>
        </div>
        <a href="{{ route('aprovacoes.fila') }}" class="text-sm text-gray-500 hover:text-gray-700">
            &larr; Voltar à fila
        </a>
    </div>

    {{-- Alerta de erro --}}
    @error('acao')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">{{ $message }}</div>
    @enderror

    {{-- Etapa atual --}}
    @if ($etapaAtual)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <p class="text-sm font-medium text-yellow-800">
                Etapa atual: <strong>{{ ucfirst($etapaAtual->nivel_exigido->value) }}</strong>
                (ciclo {{ $etapaAtual->ciclo }}, ordem {{ $etapaAtual->ordem }})
            </p>
            @if ($etapaAtual->obrigatoria_emergencial)
                <p class="text-xs text-yellow-700 mt-1">Etapa obrigatória por ser emergencial.</p>
            @endif
        </div>
    @endif

    {{-- Ações --}}
    @if ($podeAprovar && $etapaAtual)
        <div class="flex gap-3 mb-6">
            <button wire:click="$set('mostrarModalAprovar', true)"
                class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                Aprovar
            </button>
            <button wire:click="$set('mostrarModalReprovar', true)"
                class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                Reprovar
            </button>
        </div>
    @endif

    {{-- Resumo da requisição --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Dados Gerais</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Unidade</dt>
                    <dd class="text-gray-800">{{ $requisicao->unidade?->nome ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Valor estimado</dt>
                    <dd class="text-gray-800">R$ {{ number_format($requisicao->valorTotal(), 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Urgente</dt>
                    <dd class="text-gray-800">{{ $requisicao->urgente ? 'Sim' : 'Não' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Emergencial</dt>
                    <dd class="text-gray-800">{{ $requisicao->is_emergencial ? 'Sim' : 'Não' }}</dd>
                </div>
                @if ($requisicao->justificativa)
                    <div class="flex flex-col gap-1">
                        <dt class="text-gray-500">Justificativa</dt>
                        <dd class="text-gray-800">{{ $requisicao->justificativa }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Cotações --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Cotações</h2>
            @forelse ($requisicao->cotacoes->whereNull('deleted_at') as $cotacao)
                <div class="flex items-center justify-between text-sm py-1 border-b border-gray-100 last:border-0">
                    <span class="text-gray-700">{{ $cotacao->fornecedor?->nome_fantasia ?? '—' }}</span>
                    <span class="text-gray-800">R$ {{ number_format($cotacao->valor, 2, ',', '.') }}
                        @if ($cotacao->vencedora)
                            <span class="ml-1 text-xs text-green-700 font-medium">Vencedora</span>
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Nenhuma cotação registrada.</p>
            @endforelse
        </div>
    </div>

    {{-- Histórico de aprovações --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Histórico de Aprovações</h2>
        @forelse ($historico as $etapa)
            <div class="flex items-start gap-3 py-2 border-b border-gray-100 last:border-0 text-sm">
                <div class="flex-shrink-0 w-20 text-gray-500">
                    Ciclo {{ $etapa->ciclo }} / {{ $etapa->ordem }}
                </div>
                <div class="flex-1">
                    <span class="font-medium text-gray-700">{{ ucfirst($etapa->nivel_exigido->value) }}</span>
                    @if ($etapa->obrigatoria_emergencial)
                        <span class="ml-1 text-xs text-red-600">(emergencial)</span>
                    @endif
                    <span class="ml-2 inline-flex px-1.5 py-0.5 rounded text-xs font-medium
                        @if ($etapa->status === \App\Enums\StatusAprovacao::Aprovada) bg-green-100 text-green-700
                        @elseif ($etapa->status === \App\Enums\StatusAprovacao::Reprovada) bg-red-100 text-red-700
                        @elseif ($etapa->status === \App\Enums\StatusAprovacao::Pulada) bg-gray-100 text-gray-500
                        @else bg-yellow-100 text-yellow-700
                        @endif">
                        {{ ucfirst($etapa->status->value) }}
                    </span>
                    @if ($etapa->aprovador)
                        <span class="ml-2 text-gray-500">por {{ $etapa->aprovador->name }}</span>
                    @endif
                    @if ($etapa->justificativa)
                        <p class="text-gray-500 mt-0.5 italic">{{ $etapa->justificativa }}</p>
                    @endif
                    @if ($etapa->decidida_em)
                        <span class="text-gray-400 text-xs">{{ $etapa->decidida_em->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Sem histórico.</p>
        @endforelse
    </div>

    {{-- Modal Aprovar --}}
    @if ($mostrarModalAprovar)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Confirmar aprovação</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comentário (opcional)</label>
                    <textarea wire:model="justificativa" rows="3"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="Observações sobre a aprovação..."></textarea>
                    @error('justificativa') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('mostrarModalAprovar', false)"
                        class="text-sm text-gray-600 hover:text-gray-800 border border-gray-300 px-4 py-2 rounded-md">
                        Cancelar
                    </button>
                    <button wire:click="aprovar"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Confirmar aprovação
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Reprovar --}}
    @if ($mostrarModalReprovar)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Reprovar requisição</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa <span class="text-red-500">*</span></label>
                    <textarea wire:model="justificativa" rows="4"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="Informe o motivo da reprovação..."></textarea>
                    @error('justificativa') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('mostrarModalReprovar', false)"
                        class="text-sm text-gray-600 hover:text-gray-800 border border-gray-300 px-4 py-2 rounded-md">
                        Cancelar
                    </button>
                    <button wire:click="reprovar"
                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Confirmar reprovação
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
