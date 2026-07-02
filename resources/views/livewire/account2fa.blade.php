<div class="mx-auto max-w-2xl px-4 py-6">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-100">Segurança — Verificação em duas etapas</h1>
        <p class="text-sm text-slate-400">Proteja sua conta exigindo um código do app autenticador, além da senha.</p>
    </div>

    @if (session('ok'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('ok') }}</div>
    @endif
    @if (session('erro'))
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ session('erro') }}</div>
    @endif

    @php
        $btn = 'inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500';
        $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800';
    @endphp

    <div class="rounded-xl border border-slate-800 bg-slate-900 p-6 text-slate-200 shadow-sm">
        <div class="mb-4 flex items-center gap-3">
            @if ($enabled)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-sm font-semibold text-emerald-300">2FA ativo</span>
            @elseif ($pendente)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1 text-sm font-semibold text-amber-300">Configuração pendente</span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-500/15 px-3 py-1 text-sm font-semibold text-slate-300">Desativado</span>
            @endif
            @if ($obrigatorio)
                <span class="text-xs text-slate-500">Obrigatório para o seu perfil.</span>
            @endif
        </div>

        @if (! $enabled && ! $pendente)
            <p class="mb-4 text-sm text-slate-400">Ao ativar, você escaneia um QR code no app autenticador (Google Authenticator, Authy, 1Password…) e confirma com um código.</p>
            <button wire:click="habilitar" class="{{ $btn }}">Ativar 2FA</button>
        @endif

        @if ($pendente)
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <p class="mb-2 text-sm font-medium text-slate-300">1. Escaneie no app autenticador</p>
                    <div class="inline-block rounded-lg border border-slate-200 bg-white p-2">{!! $qrSvg !!}</div>
                    <p class="mt-2 break-all text-xs text-slate-500">Ou informe a chave manualmente:<br><code class="text-slate-300">{{ $secret }}</code></p>
                </div>
                <div>
                    <p class="mb-2 text-sm font-medium text-slate-300">2. Confirme o código gerado</p>
                    <form wire:submit="confirmar">
                        <input type="text" wire:model="code" inputmode="numeric" placeholder="000000"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-center text-lg tracking-widest text-slate-100 placeholder-slate-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('code') border-rose-400 @enderror">
                        @error('code')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="{{ $btn }}">Confirmar e ativar</button>
                            <button type="button" wire:click="habilitar" class="{{ $btnGhost }}">Gerar novo QR</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($enabled)
            <p class="mb-4 text-sm text-slate-400">Seu login agora pede um código do app autenticador.</p>
            <div class="flex flex-wrap gap-2">
                <button wire:click="regenerar" class="{{ $btnGhost }}">Gerar novos códigos de recuperação</button>
                @unless ($obrigatorio)
                    <button wire:click="desabilitar" wire:confirm="Desativar o 2FA da sua conta?" class="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-500/40 px-4 py-2 text-sm font-medium text-rose-400 transition hover:bg-rose-500/10">Desativar 2FA</button>
                @endunless
            </div>
        @endif

        @if (! empty($novosCodigos))
            <div class="mt-5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4">
                <p class="mb-2 text-sm font-semibold text-amber-300">Códigos de recuperação — guarde em local seguro</p>
                <p class="mb-3 text-xs text-amber-200/80">Cada código funciona uma única vez, caso você perca o acesso ao app autenticador.</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-sm text-slate-200">
                    @foreach ($novosCodigos as $rc)
                        <span class="rounded bg-slate-950 px-2 py-1 text-center">{{ $rc }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
