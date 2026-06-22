<div class="report-canvas">
    <x-page-header title="Dashboard" icon="dashboard" subtitle="Visão geral de compras e estoque" />

    {{-- Métricas --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-metric-card label="Requisições abertas" :value="$abertas" icon="document" accent="emerald" hint="Em andamento no fluxo" />
        <x-metric-card label="Aguardando triagem" :value="$aguardandoTriagem" icon="inbox" accent="amber" hint="Na fila da compradora" />
        <x-metric-card label="Aguardando aprovação" :value="$aguardandoAprovacao" icon="check-badge" accent="sky" hint="Pendentes de alçada" />
        <x-metric-card label="Pedidos emitidos" :value="$pedidosEmitidos" icon="cart" accent="emerald" />
        <x-metric-card label="Valor em pedidos emitidos" :value="'R$ '.number_format($valorEmitido, 2, ',', '.')" icon="dollar" accent="emerald" />
        <x-metric-card label="Valor em estoque" :value="'R$ '.number_format($valorEstoque, 2, ',', '.')" icon="cube" accent="sky" />
    </div>

    {{-- Pipeline + atividade recente --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-report-card title="Requisições no pipeline" subtitle="Distribuição por status" icon="chart-bar">
            @if($pipeline->isEmpty())
                <x-empty-state icon="document" title="Nenhuma requisição em andamento" message="Quando houver requisições no fluxo, elas aparecem aqui por status." />
            @else
                <div class="space-y-3">
                    @foreach($pipeline as $linha)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="text-slate-300">{{ $linha['label'] }}</span>
                                <span class="font-semibold text-white">{{ $linha['total'] }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-800">
                                <div class="h-full rounded-full bg-emerald-500/70" style="width: {{ max(4, (int) round($linha['total'] / $pipelineMax * 100)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-report-card>

        <x-report-card title="Atividade recente" subtitle="Últimas requisições" icon="clock" padding="p-0">
            @if($recentes->isEmpty())
                <div class="p-5">
                    <x-empty-state icon="document" title="Sem atividade ainda" message="As requisições mais recentes vão aparecer nesta lista." />
                </div>
            @else
                <ul class="divide-y divide-zinc-800">
                    @foreach($recentes as $req)
                        @php
                            $cor = match($req->status->value) {
                                'aguardando_triagem', 'em_triagem' => 'bg-amber-500/15 text-amber-400',
                                'em_cotacao', 'cotacao_concluida' => 'bg-sky-500/15 text-sky-400',
                                'aguardando_aprovacao' => 'bg-violet-500/15 text-violet-400',
                                'aprovada', 'em_compra', 'recebida' => 'bg-emerald-500/15 text-emerald-400',
                                'concluida' => 'bg-emerald-500/15 text-emerald-400',
                                'reprovada', 'cancelada' => 'bg-rose-500/15 text-rose-400',
                                default => 'bg-slate-500/15 text-slate-300',
                            };
                        @endphp
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-100">{{ $req->codigo ?? 'Rascunho #'.$req->id }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $req->unidade?->nome ?? '—' }} · {{ $req->created_at?->format('d/m/Y') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $cor }}">
                                {{ $labels[$req->status->value] ?? $req->status->value }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-report-card>
    </div>
</div>
