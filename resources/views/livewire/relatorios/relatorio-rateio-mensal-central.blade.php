<div class="report-canvas">
    <x-page-header
        title="Rateio Mensal da Central"
        icon="layers"
        subtitle="Distribuição mensal dos custos da central entre as unidades, com histórico de reversões."
    />

    <x-filter-bar>
        <x-filter-bar.field label="Mês">
            <input
                type="number"
                min="1"
                max="12"
                wire:model.live="filtroMes"
                placeholder="Ex.: 6"
                class="input-dark"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Ano">
            <input
                type="number"
                min="2000"
                wire:model.live="filtroAno"
                placeholder="Ex.: 2025"
                class="input-dark"
            />
        </x-filter-bar.field>
    </x-filter-bar>

    @if($rateios->isEmpty())
        <x-empty-state
            icon="layers"
            title="Nenhum rateio encontrado"
            message="Não há registros de rateio para os filtros selecionados. Ajuste o mês ou o ano para visualizar os dados."
        />
    @else
        <x-report-card title="Períodos Rateados" icon="layers" padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Período</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor da Central</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Unidades</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($rateios as $rateio)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="px-3 py-2.5 font-medium text-slate-300">
                                    {{ str_pad($rateio->mes, 2, '0', STR_PAD_LEFT) }}/{{ $rateio->ano }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold text-slate-100">
                                    R$ {{ number_format($rateio->valor_total, 2, ',', '.') }}
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-300">
                                    {{ $rateio->unidades->count() }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <button
                                        wire:click="toggleExpandir({{ $rateio->id }})"
                                        class="rounded px-2.5 py-1 text-xs font-medium transition-colors {{ $expandidoId === $rateio->id ? 'bg-slate-700 text-slate-200 hover:bg-slate-600' : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-slate-200' }}"
                                    >
                                        {{ $expandidoId === $rateio->id ? 'Recolher' : 'Detalhar' }}
                                    </button>
                                </td>
                            </tr>

                            @if($expandidoId === $rateio->id)
                                <tr>
                                    <td colspan="4" class="bg-slate-900/60 px-4 py-4">
                                        <div class="overflow-x-auto rounded-md border border-slate-800">
                                            <table class="min-w-full text-sm">
                                                <thead>
                                                    <tr class="border-b border-slate-800 bg-slate-950/40">
                                                        <th class="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                                                        <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">% Consumo</th>
                                                        <th class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor Rateado</th>
                                                        <th class="px-3 py-2.5 text-center text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                                                        @if($ehAdmin)
                                                            <th class="px-3 py-2.5"></th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-800">
                                                    @foreach($rateio->unidades as $linha)
                                                        @php
                                                            $revertido = $linha->movimentacoes->contains('tipo', \App\Enums\TipoMovimentacao::DescontoRateio);
                                                        @endphp
                                                        <tr class="hover:bg-slate-800/30 transition-colors">
                                                            <td class="px-3 py-2.5 text-slate-300">{{ $linha->unidade->nome ?? '—' }}</td>
                                                            <td class="px-3 py-2.5 text-right text-emerald-400">
                                                                {{ number_format((float) $linha->percentual_consumo * 100, 2, ',', '.') }}%
                                                            </td>
                                                            <td class="px-3 py-2.5 text-right font-semibold text-slate-100">
                                                                R$ {{ number_format($linha->valor_rateado, 2, ',', '.') }}
                                                            </td>
                                                            <td class="px-3 py-2.5 text-center">
                                                                @if($revertido)
                                                                    <span class="inline-flex items-center rounded-full bg-slate-700/60 px-2.5 py-0.5 text-xs font-medium text-slate-300">Revertido</span>
                                                                @else
                                                                    <span class="inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-400">Ativo</span>
                                                                @endif
                                                            </td>
                                                            @if($ehAdmin)
                                                                <td class="px-3 py-2.5 text-right">
                                                                    @if(! $revertido && (float) $linha->valor_rateado > 0)
                                                                        <button
                                                                            wire:click="abrirReversao({{ $linha->id }})"
                                                                            class="rounded px-2.5 py-1 text-xs font-medium text-rose-400 transition-colors hover:bg-rose-500/15 hover:text-rose-300"
                                                                        >
                                                                            Reverter
                                                                        </button>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Nenhum rateio encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-report-card>
    @endif

    {{-- Modal de reversão --}}
    @if($revertendoItemId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
                <h2 class="mb-1 text-base font-semibold text-white">Reverter Rateio</h2>
                <p class="mb-4 text-sm text-slate-400">Será gerado um crédito (Desconto de Rateio) no valor rateado desta unidade.</p>

                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">Motivo</label>
                <textarea
                    wire:model="motivoReversao"
                    rows="3"
                    placeholder="Informe o motivo da reversão"
                    class="input-dark w-full resize-none rounded-md"
                ></textarea>
                @error('motivoReversao')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror

                <div class="mt-5 flex justify-end gap-3">
                    <button
                        wire:click="cancelarReversao"
                        class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-300 transition-colors hover:bg-slate-800 hover:text-white"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmarReversao"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-rose-700"
                    >
                        Confirmar reversão
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
