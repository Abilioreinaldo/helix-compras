<div class="report-canvas">
    <x-page-header title="Fila de Triagem" icon="inbox" subtitle="Requisições aguardando triagem ou em triagem pela compradora sênior." />

    @if($erroAtendimentoEstoque)
        <div class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300 mb-4">
            {{ $erroAtendimentoEstoque }}
        </div>
    @endif

    @if($erroExpressa)
        <div class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300 mb-4">
            {{ $erroExpressa }}
        </div>
    @endif

    <x-report-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Código</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Solicitante</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unidade</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Itens / Total</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Submetida</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($requisicoes as $req)
                        @php($expressa = $this->podeAtenderExpressa($req))
                        <tr class="transition-colors hover:bg-slate-800/40 {{ $req->atrasada ? 'bg-rose-500/5' : '' }}">
                            <td class="px-4 py-3 font-mono text-slate-300">
                                {{ $req->codigo }}
                                @if ($req->atrasada) <span class="ml-1 text-xs font-semibold text-rose-400">⚠ Atrasada</span> @endif
                                @if ($req->is_emergencial) <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs bg-rose-500/15 text-rose-400">Emergencial</span> @endif
                                @if ($expressa) <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs font-medium bg-sky-500/15 text-sky-400" title="Todos os itens têm preço homologado — dispensa cotação">⚡ Expressa</span> @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $req->solicitante->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $req->unidade->nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $req->itens->count() }} iten(s)
                                @if ($req->valorTotal() > 0)
                                    <span class="block text-xs text-slate-500">R$ {{ number_format($req->valorTotal(), 2, ',', '.') }}</span>
                                @endif
                                @if ($this->temLoteVencido($req))
                                    <span class="mt-1 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-400" title="A saída debitará lote vencido">⚠️ Vencido</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-slate-500/15 text-slate-300">
                                    {{ ucwords(str_replace('_', ' ', $req->status->value)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $req->submetida_em?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="{{ route('requisicoes.detalhe', $req->id) }}" class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700 transition-colors">Ver</a>
                                @if ($req->status->value === 'aguardando_triagem')
                                    <button wire:click="iniciarTriagem({{ $req->id }})" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500 transition-colors">Iniciar</button>
                                @endif
                                @if ($req->status->value === 'em_triagem')
                                    <button wire:click="enviarParaCotacao({{ $req->id }})" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500 transition-colors">Cotação</button>
                                    <button wire:click="abrirDevolucao({{ $req->id }})" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-500 transition-colors">Devolver</button>
                                @endif
                                @if ($expressa)
                                    <button
                                        wire:click="atenderViaExpressa({{ $req->id }})"
                                        wire:confirm="Atender pela via expressa? Será gerada a cotação a partir dos preços homologados e a requisição segue direto para aprovação."
                                        class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500 transition-colors"
                                    >
                                        ⚡ Via Expressa
                                    </button>
                                @endif
                                @if ($this->todosItensTemSaldo($req))
                                    <button
                                        wire:click="atenderDoEstoque({{ $req->id }})"
                                        wire:confirm="Atender esta requisição diretamente do estoque? Os saldos serão baixados imediatamente."
                                        class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-500 transition-colors"
                                    >
                                        Atender do Estoque
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Nenhuma requisição aguardando triagem.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4 pb-4 border-t border-slate-800 pt-3">
            {{ $requisicoes->links() }}
        </div>
    </x-report-card>

    {{-- Modal devolução --}}
    @if ($devolvendo)
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-800 text-slate-100 rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-4">Devolver ao Solicitante</h2>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Motivo <span class="text-rose-400">*</span></label>
                    <textarea wire:model="observacaoDevolucao" rows="3"
                        class="input-dark w-full @error('observacaoDevolucao') border-rose-500 @enderror"
                        placeholder="Informe o que precisa ser ajustado..."></textarea>
                    @error('observacaoDevolucao') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button wire:click="$set('devolvendo', null)" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="confirmarDevolucao" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500 transition-colors">
                        Devolver
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
