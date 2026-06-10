<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Comendador Compras') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">

    {{-- Sidebar --}}
    <aside class="w-64 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col">
        <div class="h-16 flex items-center px-6 border-b border-gray-200">
            <span class="text-lg font-bold text-gray-800">Comendador Compras</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <livewire:navegacao.menu-lateral />
        </nav>
    </aside>

    {{-- Conteúdo principal --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        {{-- Topbar --}}
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <span class="text-gray-600 text-sm">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">Sair</button>
            </form>
        </header>

        {{-- Área de conteúdo --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
</body>
</html>
