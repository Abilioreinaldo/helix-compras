<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Comendador Compras') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-100 font-sans text-slate-900 antialiased">

@php
    $usuario = auth()->user();
    $iniciais = collect(explode(' ', trim($usuario->name)))
        ->filter()
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<div x-data="{ open: false }" class="flex h-screen overflow-hidden">

    {{-- Backdrop (mobile) --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        @click="open = false"
        class="fixed inset-0 z-30 bg-black/60 md:hidden"
    ></div>

    {{-- Sidebar --}}
    <aside
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-zinc-800 bg-zinc-900 transition-transform duration-200 md:static md:translate-x-0"
    >
        <div class="flex h-16 items-center gap-3 border-b border-zinc-800 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
                <x-icon name="cart" class="h-5 w-5" />
            </span>
            <div class="leading-tight">
                <p class="text-sm font-bold tracking-tight text-white">Comendador</p>
                <p class="text-[11px] font-medium text-slate-500">Compras &amp; Estoque</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <livewire:navegacao.menu-lateral />
        </nav>

        <div class="border-t border-zinc-800 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-1.5">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-sm font-semibold text-emerald-400">{{ $iniciais ?: '·' }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-100">{{ $usuario->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $usuario->email }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Conteúdo principal --}}
    <div class="flex flex-1 flex-col overflow-hidden">

        {{-- Topbar --}}
        <header class="flex h-16 flex-shrink-0 items-center gap-4 border-b border-zinc-800 bg-zinc-900 px-4 md:px-6">
            <button @click="open = true" class="text-slate-300 hover:text-white md:hidden" aria-label="Abrir menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </button>

            <div class="ml-auto flex items-center gap-4">
                <span class="hidden text-sm text-slate-300 sm:block">{{ $usuario->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-sm font-medium text-slate-400 transition-colors hover:text-white">
                        <x-icon name="logout" class="h-4 w-4" />
                        <span class="hidden sm:inline">Sair</span>
                    </button>
                </form>
            </div>
        </header>

        {{-- Área de conteúdo --}}
        <main class="flex-1 overflow-y-auto bg-slate-100 p-6">
            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
</body>
</html>
