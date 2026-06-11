<div class="bg-white rounded-lg shadow-md p-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Comendador Compras</h1>

    @error('formulario')
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="autenticar" novalidate>
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror"
                autocomplete="email"
                autofocus
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="senha" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
            <input
                id="senha"
                type="password"
                wire:model="senha"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('senha') border-red-500 @enderror"
                autocomplete="current-password"
            >
            @error('senha')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6 flex items-center">
            <input id="lembrar" type="checkbox" wire:model="lembrar" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
            <label for="lembrar" class="ml-2 text-sm text-gray-600">Manter conectado</label>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-sm transition-colors">
            Entrar
        </button>
    </form>
</div>
