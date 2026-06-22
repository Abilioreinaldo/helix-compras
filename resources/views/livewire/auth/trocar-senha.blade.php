<div class="flex min-h-screen items-center justify-center p-4">
    <div class="w-full max-w-md rounded-2xl border border-blue-500/20 bg-slate-900/80 p-8 shadow-[0_8px_32px_rgba(59,130,246,0.15)] backdrop-blur-xl">
    <h2 class="text-xl font-bold tracking-tight text-white">Troca de Senha Obrigatória</h2>
    <p class="mt-1 mb-6 text-sm text-slate-300">Defina uma nova senha para continuar.</p>

    <form wire:submit="salvar">
        <div class="mb-4">
            <label for="senha_atual" class="mb-1 block text-sm font-medium text-slate-300">Senha atual</label>
            <input
                id="senha_atual"
                type="password"
                wire:model="senha_atual"
                class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40 @error('senha_atual') border-rose-500 @enderror"
            >
            @error('senha_atual')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="nova_senha" class="mb-1 block text-sm font-medium text-slate-300">Nova senha</label>
            <input
                id="nova_senha"
                type="password"
                wire:model="nova_senha"
                class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40 @error('nova_senha') border-rose-500 @enderror"
            >
            @error('nova_senha')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="nova_senha_confirmation" class="mb-1 block text-sm font-medium text-slate-300">Confirmar nova senha</label>
            <input
                id="nova_senha_confirmation"
                type="password"
                wire:model="nova_senha_confirmation"
                class="w-full rounded-lg border border-blue-500/30 bg-black/50 px-3 py-2.5 text-sm text-slate-100 transition focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500/40"
            >
        </div>

        <button type="submit" class="w-full rounded-lg bg-blue-500 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_0_20px_rgba(59,130,246,0.35)] transition duration-300 hover:bg-blue-600 hover:shadow-[0_0_30px_rgba(59,130,246,0.6)]">
            Salvar nova senha
        </button>
    </form>
    </div>
</div>
