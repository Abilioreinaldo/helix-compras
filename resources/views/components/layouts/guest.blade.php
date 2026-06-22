<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Comendador Compras') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-black font-sans text-slate-100 antialiased">

<div class="relative flex min-h-screen items-center justify-center overflow-hidden p-4">
    {{-- Fundo SVG dark executivo --}}
    <x-auth.login-background />

    {{-- Card de autenticação em primeiro plano --}}
    <div class="relative z-10 w-full max-w-md">
        {{ $slot }}
    </div>
</div>

@livewireScripts
</body>
</html>
