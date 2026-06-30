<div class="report-canvas">
    <x-page-header title="Requisições de Material" icon="hand" subtitle="Abra e acompanhe suas requisições de material do estoque." />

    {{-- Flash notify --}}
    @if (session('notify'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('notify') }}
        </div>
    @endif

    {{-- Botão nova requisição --}}
    @if (! $mostrarFormulario)
        <div class="mb-4 flex justify-end">
            <button
                wire:click="abrirFormulario"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors"
            >
                Nova Requisição
            </button>
        </div>
    @endif

    {{-- Formulário nova RIM --}}
    @if ($mostrarFormulario)
        <x-report-card title="Abrir Nova Requisição">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Item do Estoque</label>
                    <select wire:model="saldoEstoqueId" class="input-dark w-full">
                        <option value="">Selecione o item...</option>
                        @foreach ($saldosDisponiveis as $saldo)
                            <option value="{{ $saldo->id }}">
                                {{ $saldo->descricao_item }} — {{ $saldo->deposito }} (Saldo: {{ number_format($saldo->quantidade, 3, ',', '.') }} {{ $saldo->unidade_medida }})
                            </option>
                        @endforeach
                    </select>
                    @error('saldoEstoqueId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Quantidade Solicitada</label>
                    <input
                        wire:model="quantidadeSolicitada"
                        type="number"
                        step="0.001"
                        min="0.001"
                        class="input-dark w-full"
                        placeholder="0.000"
                    />
                    @error('quantidadeSolicitada') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Justificativa</label>
                    <textarea
                        wire:model="justificativa"
                        rows="3"
                        class="input-dark w-full"
                        placeholder="Descreva o motivo da requisição..."
                    ></textarea>
                    @error('justificativa') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3">
                    <button
                        wire:click="salvar"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors"
                    >
                        Abrir Requisição
                    </button>
                    <button
                        wire:click="fecharFormulario"
                        class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition-colors"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </x-report-card>
    @endif

    {{-- Lista de requisições --}}
    @if ($requisicoes->isEmpty())
        <x-empty-state
            icon="inbox"
            title="Nenhuma requisição encontrada"
            message="Você ainda não possui requisições de material."
        />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">#</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Quantidade</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Justificativa / Motivo</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($requisicoes as $req)
                            <tr class="transition-colors hover:bg-slate-800/40" wire:key="req-{{ $req->id }}">
                                <td class="px-4 py-3 text-slate-400">{{ $req->id }}</td>
                                <td class="px-4 py-3 text-slate-200">{{ $req->saldoEstoque?->descricao_item ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format($req->quantidade_solicitada, 3, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $cor = match($req->status) {
                                            \App\Enums\StatusRequisicaoMaterial::Aberta   => 'bg-amber-500/15 text-amber-400',
                                            \App\Enums\StatusRequisicaoMaterial::Atendida => 'bg-emerald-500/15 text-emerald-400',
                                            \App\Enums\StatusRequisicaoMaterial::Recusada => 'bg-rose-500/15 text-rose-400',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $cor }}">
                                        {{ $req->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-300">
                                    {{ $req->justificativa }}
                                    @if ($req->motivo_recusa)
                                        <p class="mt-1 text-xs text-rose-400">Recusa: {{ $req->motivo_recusa }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 border-t border-slate-800 px-4 pb-4 pt-3">
                {{ $requisicoes->links() }}
            </div>
        </x-report-card>
    @endif
</div>
