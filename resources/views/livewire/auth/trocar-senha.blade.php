<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-2">Troca de Senha Obrigatória</h2>
    <p class="text-sm text-gray-600 mb-6">Defina uma nova senha para continuar.</p>

    <form wire:submit="salvar">
        <div class="mb-4">
            <label for="senha_atual" class="block text-sm font-medium text-gray-700 mb-1">Senha atual</label>
            <input
                id="senha_atual"
                type="password"
                wire:model="senha_atual"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('senha_atual') border-red-500 @enderror"
            >
            @error('senha_atual')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="nova_senha" class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
            <input
                id="nova_senha"
                type="password"
                wire:model="nova_senha"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nova_senha') border-red-500 @enderror"
            >
            @error('nova_senha')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="nova_senha_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar nova senha</label>
            <input
                id="nova_senha_confirmation"
                type="password"
                wire:model="nova_senha_confirmation"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-sm transition-colors">
            Salvar nova senha
        </button>
    </form>
</div>
