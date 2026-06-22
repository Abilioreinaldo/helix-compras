<div class="flex min-h-screen flex-col lg:flex-row">
    {{-- ─── ESQUERDA: branding + features + fluxo + cards (oculto no mobile) ─── --}}
    <div class="hidden flex-col justify-center gap-8 p-12 lg:flex lg:w-1/2 xl:p-16">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30 shadow-[0_0_24px_rgba(59,130,246,0.35)]">
                <x-icon name="cart" class="h-6 w-6" />
            </span>
            <span class="text-lg font-bold tracking-tight text-white">Comendador Compras</span>
        </div>

        <div>
            <h1 class="text-4xl font-bold leading-tight tracking-tight text-white xl:text-5xl">
                Gestão Inteligente de<br><span class="bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent">Compras Corporativas</span>
            </h1>
            <p class="mt-4 max-w-md text-slate-300">Da requisição ao pagamento, com alçada, auditoria e controle por unidade — numa plataforma só.</p>
        </div>

        {{-- Features --}}
        <ul class="space-y-2.5">
            @foreach (['Gestão multiunidade centralizada', 'Aprovação por alçada multinível', 'Trilha de auditoria completa'] as $feature)
                <li class="flex items-center gap-2.5 text-slate-200">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-500/15 text-cyan-300">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    {{ $feature }}
                </li>
            @endforeach
        </ul>

        {{-- Diagrama de fluxo --}}
        <div>
            <p class="mb-2 text-xs font-medium uppercase tracking-wider text-slate-400">Fluxo de compra</p>
            <svg viewBox="0 0 560 64" class="w-full max-w-lg" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="fluxo-conn" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#3b82f6" /><stop offset="100%" stop-color="#06b6d4" />
                    </linearGradient>
                </defs>
                @php $passos = ['Requisição', 'Cotação', 'Aprovação', 'Pedido']; @endphp
                @foreach ($passos as $i => $passo)
                    <g transform="translate({{ $i * 140 }}, 16)">
                        @if ($i > 0)
                            <line x1="-20" y1="16" x2="0" y2="16" stroke="url(#fluxo-conn)" stroke-width="2" />
                        @endif
                        <rect x="0" y="0" width="120" height="32" rx="8" fill="rgba(59,130,246,0.08)" stroke="rgba(59,130,246,0.4)" stroke-width="1.5" />
                        <text x="60" y="21" text-anchor="middle" fill="#e2e8f0" font-size="13" font-family="sans-serif">{{ $passo }}</text>
                    </g>
                @endforeach
            </svg>
        </div>

        {{-- Cards de indicadores (ilustrativos) --}}
        <div class="grid max-w-lg grid-cols-2 gap-4">
            @php
                $cards = [
                    ['icon' => 'truck', 'valor' => '+2.450', 'label' => 'Fornecedores ativos', 'accent' => 'text-blue-300'],
                    ['icon' => 'dollar', 'valor' => 'R$ 1.248.750', 'label' => 'Economia gerada (+18,6%)', 'accent' => 'text-cyan-300'],
                    ['icon' => 'document', 'valor' => '134', 'label' => 'Contratos ativos', 'accent' => 'text-blue-300'],
                    ['icon' => 'check-badge', 'valor' => '100%', 'label' => 'Conformidade', 'accent' => 'text-cyan-300'],
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="rounded-xl border border-blue-500/15 bg-slate-900/50 p-4 backdrop-blur-sm">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/10 {{ $card['accent'] }}"><x-icon :name="$card['icon']" class="h-4 w-4" /></span>
                    <div class="mt-2 text-xl font-bold text-white">{{ $card['valor'] }}</div>
                    <div class="text-xs text-slate-400">{{ $card['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ─── DIREITA: form glass ─── --}}
    <div class="flex w-full items-center justify-center p-6 lg:w-1/2">
        <div class="w-full max-w-md rounded-2xl border border-blue-500/20 bg-slate-900/80 p-8 shadow-[0_8px_32px_rgba(59,130,246,0.15)] backdrop-blur-xl">
            {{-- Logo (visível também no mobile, onde a coluna esquerda some) --}}
            <div class="mb-6 flex flex-col items-center text-center lg:items-start lg:text-left">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30 shadow-[0_0_24px_rgba(59,130,246,0.35)] lg:hidden">
                    <x-icon name="cart" class="h-6 w-6" />
                </span>
                <h2 class="text-2xl font-bold tracking-tight text-white">Acesse sua conta</h2>
                <p class="mt-1 text-sm text-slate-300">Entre para continuar para o painel.</p>
            </div>

            @error('formulario')
                <div class="mb-4 rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
            @enderror

            <form wire:submit="autenticar" novalidate>
                <div class="mb-4">
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-200">E-mail</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" autofocus
                        class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40 @error('email') border-rose-500 @enderror">
                    @error('email')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label for="senha" class="mb-1 block text-sm font-medium text-slate-200">Senha</label>
                    <input id="senha" type="password" wire:model="senha" autocomplete="current-password"
                        class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40 @error('senha') border-rose-500 @enderror">
                    @error('senha')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="mb-6 flex items-center">
                    <input id="lembrar" type="checkbox" wire:model="lembrar" class="h-4 w-4 rounded border-blue-500/40 bg-black/50 text-blue-500 focus:ring-blue-500/40">
                    <label for="lembrar" class="ml-2 text-sm text-slate-300">Manter conectado</label>
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-blue-500 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_0_20px_rgba(59,130,246,0.35)] transition duration-300 hover:bg-blue-600 hover:shadow-[0_0_30px_rgba(59,130,246,0.6)]">
                    Entrar
                </button>
            </form>

            {{-- SSO (em breve — login social não habilitado) --}}
            <div class="my-5 flex items-center gap-3 text-xs text-slate-500">
                <span class="h-px flex-1 bg-slate-700/60"></span>ou<span class="h-px flex-1 bg-slate-700/60"></span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach (['Google', 'Microsoft'] as $sso)
                    <button type="button" disabled title="Login social em breve"
                        class="cursor-not-allowed rounded-lg border border-slate-700 bg-slate-800/40 px-3 py-2 text-sm font-medium text-slate-400 opacity-70">
                        {{ $sso }}
                    </button>
                @endforeach
            </div>

            <p class="mt-6 text-center text-sm text-slate-400">
                Precisa de acesso? <a href="#" class="font-medium text-cyan-400 hover:text-cyan-300">Fale com um especialista</a>
            </p>
        </div>
    </div>
</div>
