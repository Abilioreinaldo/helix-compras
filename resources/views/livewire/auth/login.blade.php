<div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-xl">
    <div class="mb-6 flex flex-col items-center text-center">
        <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
            <x-icon name="cart" class="h-6 w-6" />
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-white">Comendador Compras</h1>
        <p class="mt-1 text-sm text-slate-400">Acesse sua conta para continuar</p>
    </div>

    @error('formulario')
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="autenticar" novalidate>
        <div class="mb-4">
            <label for="email" class="mb-1 block text-sm font-medium text-slate-300">E-mail</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                class="input-dark w-full @error('email') border-rose-500 @enderror"
                autocomplete="email"
                autofocus
            >
            @error('email')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="senha" class="mb-1 block text-sm font-medium text-slate-300">Senha</label>
            <input
                id="senha"
                type="password"
                wire:model="senha"
                class="input-dark w-full @error('senha') border-rose-500 @enderror"
                autocomplete="current-password"
            >
            @error('senha')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6 flex items-center">
            <input id="lembrar" type="checkbox" wire:model="lembrar" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-emerald-500 focus:ring-emerald-500/40">
            <label for="lembrar" class="ml-2 text-sm text-slate-400">Manter conectado</label>
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500">
            Entrar
        </button>
    </form>
</div>
