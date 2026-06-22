<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Comendador Compras') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">

<div class="relative min-h-screen overflow-hidden">
    {{-- Fundo SVG neon --}}
    <x-auth.login-background />

    {{-- Cada tela de auth controla seu próprio layout (login = split; troca = centrado). --}}
    <div class="relative z-10 min-h-screen">
        {{ $slot }}
    </div>
</div>

@livewireScripts
</body>
</html>
