<div class="report-canvas">
    @php
        $statusValue = $requisicao->status->value;
        $statusBadge = match(true) {
            in_array($statusValue, ['aguardando_triagem', 'em_triagem']) => 'bg-amber-500/15 text-amber-400',
            in_array($statusValue, ['aguardando_cotacao', 'em_cotacao']) => 'bg-sky-500/15 text-sky-400',
            $statusValue === 'aguardando_aprovacao' => 'bg-violet-500/15 text-violet-400',
            in_array($statusValue, ['aprovada', 'recebida', 'concluida']) => 'bg-emerald-500/15 text-emerald-400',
            in_array($statusValue, ['reprovada', 'cancelada']) => 'bg-rose-500/15 text-rose-400',
            default => 'bg-slate-500/15 text-slate-300',
        };
    @endphp

    <x-page-header
        title="{{ $requisicao->codigo ?? 'Rascunho' }}"
        icon="document"
        subtitle="Criada em {{ $requisicao->created_at->format('d/m/Y H:i') }} por {{ $requisicao->solicitante->name ?? '—' }}"
    >
        <x-slot name="actions">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $statusBadge }}">
                {{ ucwords(str_replace('_', ' ', $requisicao->status->value)) }}
            </span>
            @if ($requisicao->urgente)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-500/15 text-amber-400">Urgente</span>
            @endif
            @if ($requisicao->is_emergencial)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-rose-500/15 text-rose-400">Emergencial</span>
            @endif
            @if ($requisicao->atrasada)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-rose-500/15 text-rose-400">Atrasada</span>
            @endif
            @if ($requisicao->escalada_verba)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-500/15 text-amber-400">Escalada (verba)</span>
            @endif
        </x-slot>
    </x-page-header>

    {{-- Ações --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @if ($requisicao->status->value === 'em_cotacao' && auth()->user()->temPerfil(\App\Enums\Perfil::CompradoraSenior))
            <a href="{{ route('compradora.cotacoes', $requisicao->id) }}"
                class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                Gerenciar Cotações
            </a>
        @endif
        @if ($requisicao->status->value === 'aguardando_aprovacao' && auth()->user()->temPerfil(\App\Enums\Perfil::Aprovador))
            <a href="{{ route('aprovacoes.painel', $requisicao->id) }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                Aprovar
            </a>
        @endif
        @if ($requisicao->status->permiteEdicao())
            <a href="{{ route('requisicoes.editar', $requisicao->id) }}"
                class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700 transition-colors">
                Editar
            </a>
        @endif
        @if (! $requisicao->status->ehTerminal())
            <button wire:click="abrirModalCancelar"
                class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                Cancelar
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Dados gerais --}}
        <x-report-card title="Dados Gerais">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-400">Unidade</dt>
                    <dd class="text-slate-200">{{ $requisicao->unidade->nome ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-400">Centro de Custo</dt>
                    <dd class="text-slate-200">{{ $requisicao->centroCusto->nome ?? '—' }}</dd>
                </div>
                @if ($requisicao->obra)
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Obra</dt>
                        <dd class="text-slate-200">{{ $requisicao->obra->id }}</dd>
                    </div>
                    @if ($requisicao->consumo_verba_no_submit)
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Consumo verba (submit)</dt>
                            <dd class="text-slate-200">R$ {{ number_format($requisicao->consumo_verba_no_submit, 2, ',', '.') }}</dd>
                        </div>
                    @endif
                @endif
                @if ($requisicao->faixaAlcada)
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Alçada</dt>
                        <dd class="text-slate-200">{{ $requisicao->faixaAlcada->nome }}</dd>
                    </div>
                @endif
                @if ($requisicao->justificativa)
                    <div>
                        <dt class="text-slate-400 mb-1">Justificativa</dt>
                        <dd class="text-slate-200 bg-zinc-950/40 border border-zinc-800 p-2 rounded text-xs">{{ $requisicao->justificativa }}</dd>
                    </div>
                @endif
            </dl>
        </x-report-card>

        {{-- Itens --}}
        <x-report-card title="Itens" padding="p-0">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor unit.</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach ($requisicao->itens as $item)
                        <tr class="hover:bg-zinc-800/40 transition-colors">
                            <td class="px-4 py-2 text-slate-300">{{ $item->descricao }}</td>
                            <td class="px-4 py-2 text-right text-slate-400">{{ $item->quantidade }} {{ $item->unidade_medida }}</td>
                            <td class="px-4 py-2 text-right text-slate-400">
                                {{ $item->valor_unitario_estimado ? 'R$ '.number_format($item->valor_unitario_estimado, 2, ',', '.') : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right text-slate-300 font-medium">
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
                    <tr class="border-t border-zinc-800">
                        <td colspan="3" class="px-4 pt-2 pb-3 text-right text-sm font-medium text-slate-400">Total estimado</td>
                        <td class="px-4 pt-2 pb-3 text-right text-sm font-bold text-slate-100">
                            R$ {{ number_format($requisicao->valorTotal(), 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </x-report-card>
    </div>

    {{-- Histórico de status --}}
    <x-report-card title="Histórico">
        <ol class="relative border-l border-zinc-800 ml-3 space-y-4">
            @foreach ($requisicao->logs->sortBy('created_at') as $log)
                <li class="ml-4">
                    <div class="absolute w-2 h-2 bg-zinc-600 rounded-full -left-1 mt-1.5"></div>
                    <p class="text-sm text-slate-200">
                        <span class="font-medium">{{ ucwords(str_replace('_', ' ', $log->status_novo->value)) }}</span>
                        @if ($log->status_anterior)
                            <span class="text-slate-500"> ← {{ ucwords(str_replace('_', ' ', $log->status_anterior->value)) }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-400">
                        {{ $log->created_at->format('d/m/Y H:i') }}
                        @if ($log->usuario) · {{ $log->usuario->name }} @endif
                        @if ($log->automatico) · <em>automático</em> @endif
                    </p>
                    @if ($log->observacao)
                        <p class="text-xs text-slate-400 mt-0.5 italic">{{ $log->observacao }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </x-report-card>

    {{-- Modal cancelar --}}
    @if ($mostrarModalCancelar)
        <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">Cancelar Requisição</h2>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Motivo <span class="text-rose-400">*</span></label>
                    <textarea wire:model="motivoCancelamento" rows="3"
                        class="input-dark w-full @error('motivoCancelamento') border-rose-500 @enderror"></textarea>
                    @error('motivoCancelamento') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button wire:click="$set('mostrarModalCancelar', false)"
                        class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700 transition-colors">
                        Voltar
                    </button>
                    <button wire:click="cancelarRequisicao"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
