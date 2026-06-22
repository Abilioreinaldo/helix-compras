<div class="report-canvas">
    <nav class="mb-3 flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-300">Dashboard</a>
        <span>›</span>
        <span class="text-slate-400">Pagamentos</span>
    </nav>

    <x-page-header title="Contas a Pagar" icon="dollar" subtitle="Pagamentos gerados a partir dos pedidos de compra.">
        <x-slot:actions>
            <a href="{{ route('pagamentos.agendamentos') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700">Agendamentos</a>
            <a href="{{ route('pagamentos.reconciliacao') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700">Reconciliação</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Totais --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card label="Total a pagar" :value="'R$ '.number_format($totalAPagar, 2, ',', '.')" icon="dollar" accent="amber" />
        <x-metric-card label="Pago este mês" :value="'R$ '.number_format($totalPagoMes, 2, ',', '.')" icon="check-badge" accent="emerald" />
        <x-metric-card label="Vencido" :value="'R$ '.number_format($totalVencido, 2, ',', '.')" icon="bolt" accent="rose" />
        <x-metric-card label="Agendado" :value="'R$ '.number_format($totalAgendado, 2, ',', '.')" icon="clock" accent="sky" />
    </div>

    <x-filter-bar>
        <x-filter-bar.field label="Status">
            <select wire:model.live="filtroStatus" class="input-dark">
                <option value="">Todos</option>
                @foreach (\App\Enums\StatusPagamento::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->rotulo() }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Fornecedor">
            <select wire:model.live="filtroFornecedorId" class="input-dark">
                <option value="">Todos</option>
                @foreach ($fornecedores as $f)
                    <option value="{{ $f->id }}">{{ $f->nome_fantasia }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Banco">
            <select wire:model.live="filtroBancoId" class="input-dark">
                <option value="">Todos</option>
                @foreach ($bancos as $b)
                    <option value="{{ $b->id }}">{{ $b->nome }}</option>
                @endforeach
            </select>
        </x-filter-bar.field>
        <x-filter-bar.field label="Vencimento até">
            <input type="date" wire:model.live="filtroVencimentoAte" class="input-dark">
        </x-filter-bar.field>
    </x-filter-bar>

    @if ($pagamentos->isEmpty())
        <x-empty-state icon="dollar" title="Nenhum pagamento" message="As contas a pagar aparecem aqui quando um pedido de compra é emitido." />
    @else
        <x-report-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40">
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">NF</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Vencimento</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Valor</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Pago</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ($pagamentos as $pag)
                            @php
                                $cor = match($pag->status->value) {
                                    'pago' => 'bg-emerald-500/15 text-emerald-400',
                                    'agendado' => 'bg-sky-500/15 text-sky-400',
                                    'parcial' => 'bg-amber-500/15 text-amber-400',
                                    'vencido' => 'bg-rose-500/15 text-rose-400',
                                    'cancelado' => 'bg-slate-500/15 text-slate-400',
                                    default => 'bg-slate-500/15 text-slate-300',
                                };
                                $venceu = $pag->ehVencido();
                            @endphp
                            <tr class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-4 py-3 text-slate-300">{{ $pag->numero_nf ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-200">{{ $pag->fornecedor?->nome_fantasia ?? '—' }}</td>
                                <td class="px-4 py-3 {{ $venceu ? 'font-medium text-rose-400' : 'text-slate-400' }}">
                                    {{ $pag->data_vencimento?->format('d/m/Y') }}
                                    @if ($venceu)<span class="block text-xs">vencido</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-200">R$ {{ number_format((float) $pag->valor_total, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-slate-300">R$ {{ number_format((float) $pag->valor_pago, 2, ',', '.') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $cor }}">{{ $pag->status->rotulo() }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        @if ($pag->status->emAberto())
                                            <button wire:click="abrirRegistrar({{ $pag->id }})" class="text-xs font-medium text-emerald-400 hover:text-emerald-300">Registrar</button>
                                            <button wire:click="abrirAgendar({{ $pag->id }})" class="text-xs text-sky-400 hover:text-sky-300">Agendar</button>
                                        @endif
                                        @if ($pag->status->value !== 'pago' && $pag->status->value !== 'cancelado')
                                            <button wire:click="abrirCancelar({{ $pag->id }})" class="text-xs text-rose-400 hover:text-rose-300">Cancelar</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-800 px-4 py-3">{{ $pagamentos->links() }}</div>
        </x-report-card>
    @endif

    {{-- Modal Registrar --}}
    @if ($mostrarRegistrar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-lg rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-slate-100">Registrar pagamento</h2>
                @error('formulario')<div class="mb-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-300">{{ $message }}</div>@enderror
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Valor pago (R$)</label>
                        <input type="number" step="0.01" min="0" wire:model="valorPago" class="input-dark w-full @error('valorPago') border-rose-500 @enderror">
                        @error('valorPago')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Data do pagamento</label>
                        <input type="date" wire:model="dataPagamento" class="input-dark w-full @error('dataPagamento') border-rose-500 @enderror">
                        @error('dataPagamento')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Método</label>
                        <select wire:model.live="metodo" class="input-dark w-full @error('metodo') border-rose-500 @enderror">
                            <option value="">Selecione...</option>
                            @foreach (\App\Enums\MetodoPagamento::cases() as $m)
                                <option value="{{ $m->value }}">{{ $m->rotulo() }}</option>
                            @endforeach
                        </select>
                        @error('metodo')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    @if ($metodo !== '' && $metodo !== 'dinheiro')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Banco</label>
                            <select wire:model="bancoId" class="input-dark w-full">
                                <option value="">Selecione...</option>
                                @foreach ($bancos as $b)
                                    <option value="{{ $b->id }}">{{ $b->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($metodo === 'cheque')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Número do cheque</label>
                            <input type="text" wire:model="numeroCheque" class="input-dark w-full">
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-300">Referência bancária (NSU/doc)</label>
                        <input type="text" wire:model="referenciaBanco" class="input-dark w-full" placeholder="Para reconciliação">
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button wire:click="$set('mostrarRegistrar', false)" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700">Cancelar</button>
                    <button wire:click="registrar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">Confirmar pagamento</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Agendar --}}
    @if ($mostrarAgendar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-slate-100">Agendar pagamento</h2>
                @error('formulario')<div class="mb-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-300">{{ $message }}</div>@enderror
                <label class="mb-1 block text-sm font-medium text-slate-300">Data</label>
                <input type="date" wire:model="dataAgendamento" class="input-dark w-full @error('dataAgendamento') border-rose-500 @enderror">
                @error('dataAgendamento')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                <div class="mt-5 flex justify-end gap-3">
                    <button wire:click="$set('mostrarAgendar', false)" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700">Cancelar</button>
                    <button wire:click="agendar" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">Agendar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Cancelar --}}
    @if ($mostrarCancelar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-slate-100">Cancelar pagamento</h2>
                @error('formulario')<div class="mb-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-300">{{ $message }}</div>@enderror
                <label class="mb-1 block text-sm font-medium text-slate-300">Motivo</label>
                <textarea wire:model="motivoCancelamento" rows="3" class="input-dark w-full @error('motivoCancelamento') border-rose-500 @enderror"></textarea>
                @error('motivoCancelamento')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                <div class="mt-5 flex justify-end gap-3">
                    <button wire:click="$set('mostrarCancelar', false)" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700">Voltar</button>
                    <button wire:click="cancelar" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500">Confirmar cancelamento</button>
                </div>
            </div>
        </div>
    @endif
</div>
