<div class="report-canvas">
    <x-page-header title="Saldos de Estoque" icon="cube" subtitle="Saldo atual por depósito nas unidades onde você é almoxarife." />

    {{-- Flash --}}
    @if(session('notify'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('notify') }}
        </div>
    @endif

    {{-- Painel: Itens a repor --}}
    @if($itensARepor->isNotEmpty())
        <x-report-card padding="p-0" class="mb-6">
            <div class="px-4 py-3 border-b border-slate-800">
                <h2 class="text-sm font-semibold text-amber-400">Itens a repor</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Un. Medida</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Mínimo</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Saldo Atual</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">A repor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($itensARepor as $repor)
                            <tr class="transition-colors hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-slate-300">{{ $repor->item_descricao }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $repor->unidade_medida ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format((float) $repor->quantidade_minima, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right {{ (float) $repor->saldo_atual <= 0 ? 'font-medium text-rose-400' : 'text-slate-300' }}">
                                    {{ number_format((float) $repor->saldo_atual, 3, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-amber-400">
                                    {{ number_format((float) $repor->quantidade_sugerida, 3, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-report-card>
    @endif

    {{-- Filtros --}}
    <x-filter-bar>
        <x-filter-bar.field label="Buscar" class="min-w-[220px] flex-1">
            <input
                wire:model.live.debounce.400ms="busca"
                type="text"
                placeholder="Buscar item..."
                class="input-dark w-full"
            />
        </x-filter-bar.field>
        <x-filter-bar.field label="Depósito">
            <select wire:model.live="deposito" class="input-dark">
                <option value="">Todos os depósitos</option>
                @foreach($depositos as $dep)
                    <option value="{{ $dep }}">{{ $dep }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
    </x-filter-bar>

    @if($saldos->isEmpty())
        <x-empty-state
            icon="cube"
            title="Nenhum saldo encontrado"
            message="Nenhum saldo encontrado para os filtros selecionados."
        />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Depósito</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Quantidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Validade</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">CMP</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor Total</th>
                            <th class="px-4 py-2.5 text-center text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($saldos as $saldo)
                            @php
                                $emAlerta = $saldo->item_catalogo_id && in_array($saldo->item_catalogo_id, $idsEmAlerta);
                                $linhaClass = (float) $saldo->quantidade <= 0 ? 'bg-rose-500/5' : ($emAlerta ? 'bg-amber-500/5' : '');
                            @endphp
                            <tr class="transition-colors hover:bg-slate-800/40 {{ $linhaClass }}">
                                <td class="px-4 py-3 text-slate-300">
                                    {{ $saldo->descricao_item }}
                                    @if($saldo->unidade_medida)
                                        <span class="ml-1 text-xs text-slate-500">{{ $saldo->unidade_medida }}</span>
                                    @endif
                                    @if($emAlerta)
                                        <span class="ml-2 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-400">
                                            Abaixo do mínimo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ $saldo->deposito }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $saldo->unidade->nome }}</td>
                                <td class="px-4 py-3 text-right {{ (float) $saldo->quantidade <= 0 ? 'font-medium text-rose-400' : 'text-slate-300' }}">
                                    {{ number_format($saldo->quantidade, 3, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-slate-300">
                                    @include('partials.validade-lote', ['v' => $validades->get($saldo->id)])
                                </td>
                                <td class="px-4 py-3 text-right text-slate-400">R$ {{ number_format($saldo->custo_medio_ponderado, 4, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-300">R$ {{ number_format($saldo->valor_total, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center space-x-2 whitespace-nowrap">
                                    @if($saldo->item_catalogo_id)
                                        <button
                                            wire:click="abrirModalMinimo({{ $saldo->id }})"
                                            class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700 transition-colors"
                                        >
                                            Definir mínimo
                                        </button>
                                    @endif
                                    @if((float) $saldo->quantidade > 0)
                                        <button
                                            wire:click="abrirTransferencia({{ $saldo->id }})"
                                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500 transition-colors"
                                        >
                                            Transferir
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 px-4 pb-4 border-t border-slate-800 pt-3">
                {{ $saldos->links() }}
            </div>
        </x-report-card>
    @endif

    {{-- Modal: Definir Estoque Mínimo --}}
    @if($mostrarModalMinimo)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-1">Definir Estoque Mínimo</h2>
                <p class="text-sm text-slate-400 mb-4">{{ $minimoDescricaoItem }}</p>

                @if($errors->has('minimoQuantidade'))
                    <div class="mb-3 text-sm text-rose-400">{{ $errors->first('minimoQuantidade') }}</div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-1">
                        Quantidade mínima <span class="text-slate-500 font-normal">(0 = remover)</span>
                    </label>
                    <input
                        wire:model="minimoQuantidade"
                        type="number"
                        min="0"
                        step="0.001"
                        class="input-dark w-full"
                        placeholder="Ex.: 10"
                        autofocus
                    />
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        wire:click="fecharModalMinimo"
                        class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="salvarMinimo"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors"
                    >
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Transferência entre unidades --}}
    @if($transferindoSaldoId !== null)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-1">Transferir entre unidades</h2>
                <p class="text-sm text-slate-400 mb-4">{{ $transferDescricaoItem }}</p>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-slate-300 mb-1">Unidade de destino</label>
                    <select wire:model="transferDestinoId" class="input-dark w-full">
                        <option value="">Selecione...</option>
                        @foreach($unidadesDestino as $u)
                            <option value="{{ $u->id }}">{{ $u->nome }}</option>
                        @endforeach
                    </select>
                    @error('transferDestinoId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-slate-300 mb-1">Quantidade</label>
                    <input wire:model="transferQuantidade" type="number" min="0.001" step="0.001"
                        class="input-dark w-full" placeholder="Ex.: 10" />
                    @error('transferQuantidade') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-1">Motivo <span class="text-slate-500 font-normal">(opcional)</span></label>
                    <textarea wire:model="transferMotivo" rows="2" class="input-dark w-full" placeholder="Ex.: realocação entre filiais"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="cancelarTransferencia" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">Cancelar</button>
                    <button wire:click="confirmarTransferencia" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">Transferir</button>
                </div>
            </div>
        </div>
    @endif
</div>
