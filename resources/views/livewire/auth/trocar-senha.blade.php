<div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-xl">
    <h2 class="text-xl font-bold tracking-tight text-white">Troca de Senha Obrigatória</h2>
    <p class="mt-1 mb-6 text-sm text-slate-400">Defina uma nova senha para continuar.</p>

    <form wire:submit="salvar">
        <div class="mb-4">
            <label for="senha_atual" class="mb-1 block text-sm font-medium text-slate-300">Senha atual</label>
            <input
                id="senha_atual"
                type="password"
                wire:model="senha_atual"
                class="input-dark w-full @error('senha_atual') border-rose-500 @enderror"
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
                class="input-dark w-full @error('nova_senha') border-rose-500 @enderror"
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
                class="input-dark w-full"
            >
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500">
            Salvar nova senha
        </button>
    </form>
</div>
