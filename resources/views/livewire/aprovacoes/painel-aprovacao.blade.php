<div class="report-canvas">
    <x-page-header
        title="{{ $requisicao->codigo ?? '—' }}"
        icon="check-badge"
        subtitle="Solicitada por {{ $requisicao->solicitante?->name ?? '—' }} &mdash; {{ $requisicao->unidade?->nome ?? '—' }}"
    />

    {{-- Alerta de erro de ação --}}
    @error('acao')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    {{-- Etapa atual --}}
    @if ($etapaAtual)
        <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3">
            <p class="text-sm font-medium text-amber-400">
                Etapa atual: <strong>{{ ucfirst($etapaAtual->nivel_exigido->value) }}</strong>
                (ciclo {{ $etapaAtual->ciclo }}, ordem {{ $etapaAtual->ordem }})
            </p>
            @if ($etapaAtual->obrigatoria_emergencial)
                <p class="mt-1 text-xs text-amber-400/80">Etapa obrigatória por ser emergencial.</p>
            @endif
        </div>
    @endif

    {{-- Ações --}}
    @if ($podeAprovar && $etapaAtual)
        <div class="mb-6 flex gap-3">
            <button wire:click="$set('mostrarModalAprovar', true)"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
                Aprovar
            </button>
            <button wire:click="$set('mostrarModalReprovar', true)"
                class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                Reprovar
            </button>
        </div>
    @endif

    {{-- Dados Gerais + Cotações --}}
    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-report-card title="Dados Gerais">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-400">Unidade</dt>
                    <dd class="text-slate-200">{{ $requisicao->unidade?->nome ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-400">Valor estimado</dt>
                    <dd class="text-slate-200">R$ {{ number_format($requisicao->valorTotal(), 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-400">Urgente</dt>
                    <dd class="text-slate-200">{{ $requisicao->urgente ? 'Sim' : 'Não' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-400">Emergencial</dt>
                    <dd class="text-slate-200">{{ $requisicao->is_emergencial ? 'Sim' : 'Não' }}</dd>
                </div>
                @if ($requisicao->justificativa)
                    <div class="flex flex-col gap-1">
                        <dt class="text-slate-400">Justificativa</dt>
                        <dd class="text-slate-200">{{ $requisicao->justificativa }}</dd>
                    </div>
                @endif
            </dl>
        </x-report-card>

        <x-report-card title="Cotações">
            @forelse ($requisicao->cotacoes->whereNull('deleted_at') as $cotacao)
                <div class="flex items-center justify-between border-b border-slate-800 py-1.5 text-sm last:border-0">
                    <span class="text-slate-300">{{ $cotacao->fornecedor?->nome_fantasia ?? '—' }}</span>
                    <span class="text-slate-200">
                        R$ {{ number_format($cotacao->valor, 2, ',', '.') }}
                        @if ($cotacao->vencedora)
                            <span class="ml-1 text-xs font-medium text-emerald-400">Vencedora</span>
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Nenhuma cotação registrada.</p>
            @endforelse
        </x-report-card>
    </div>

    {{-- Histórico de Aprovações --}}
    <x-report-card title="Histórico de Aprovações" padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ciclo / Ordem</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Nível</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Aprovador</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Justificativa</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($historico as $etapa)
                        <tr class="transition-colors hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-400">
                                {{ $etapa->ciclo }} / {{ $etapa->ordem }}
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ ucfirst($etapa->nivel_exigido->value) }}
                                @if ($etapa->obrigatoria_emergencial)
                                    <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs bg-rose-500/15 text-rose-400">emergencial</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium
                                    @if ($etapa->status === \App\Enums\StatusAprovacao::Aprovada) bg-emerald-500/15 text-emerald-400
                                    @elseif ($etapa->status === \App\Enums\StatusAprovacao::Reprovada) bg-rose-500/15 text-rose-400
                                    @elseif ($etapa->status === \App\Enums\StatusAprovacao::Pulada) bg-slate-500/15 text-slate-300
                                    @else bg-amber-500/15 text-amber-400
                                    @endif">
                                    {{ ucfirst($etapa->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $etapa->aprovador?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-400 italic">
                                {{ $etapa->justificativa ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $etapa->decidida_em?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Sem histórico.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-report-card>

    {{-- Voltar --}}
    <div class="mt-6">
        <a href="{{ route('aprovacoes.fila') }}"
            class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
            &larr; Voltar à fila
        </a>
    </div>

    {{-- Modal Aprovar --}}
    @if ($mostrarModalAprovar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-full max-w-md rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-slate-100">Confirmar aprovação</h3>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Comentário (opcional)</label>
                    <textarea wire:model="justificativa" rows="3"
                        class="input-dark w-full"
                        placeholder="Observações sobre a aprovação..."></textarea>
                    @error('justificativa') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('mostrarModalAprovar', false)"
                        class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="aprovar"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
                        Confirmar aprovação
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Reprovar --}}
    @if ($mostrarModalReprovar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-full max-w-md rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-slate-100">Reprovar requisição</h3>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Justificativa <span class="text-rose-400">*</span></label>
                    <textarea wire:model="justificativa" rows="4"
                        class="input-dark w-full"
                        placeholder="Informe o motivo da reprovação..."></textarea>
                    @error('justificativa') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('mostrarModalReprovar', false)"
                        class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="reprovar"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                        Confirmar reprovação
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
