<div class="rounded-2xl border border-blue-500/20 bg-slate-900/70 p-8 shadow-[0_8px_32px_rgba(59,130,246,0.15)] backdrop-blur-xl">
    <div class="mb-6 flex flex-col items-center text-center">
        <span class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30 shadow-[0_0_24px_rgba(59,130,246,0.35)]">
            <x-icon name="cart" class="h-7 w-7" />
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-white">Comendador Compras</h1>
        <p class="mt-1 text-sm text-slate-300">Acesse sua conta para continuar</p>
    </div>

    @error('formulario')
        <div class="mb-4 rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="autenticar" novalidate>
        <div class="mb-4">
            <label for="email" class="mb-1 block text-sm font-medium text-slate-200">E-mail</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40 @error('email') border-rose-500 @enderror"
                autocomplete="email"
                autofocus
            >
            @error('email')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="senha" class="mb-1 block text-sm font-medium text-slate-200">Senha</label>
            <input
                id="senha"
                type="password"
                wire:model="senha"
                class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40 @error('senha') border-rose-500 @enderror"
                autocomplete="current-password"
            >
            @error('senha')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
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
</div>
