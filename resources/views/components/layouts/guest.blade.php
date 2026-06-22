<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Comendador Compras') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-zinc-950 font-sans text-slate-100 antialiased">

<div class="relative flex min-h-screen items-center justify-center overflow-hidden p-4">
    {{-- brilho de fundo discreto --}}
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(16,185,129,0.10),transparent)]"></div>
    <div class="relative w-full max-w-md">
        {{ $slot }}
    </div>
</div>

@livewireScripts
</body>
</html>
