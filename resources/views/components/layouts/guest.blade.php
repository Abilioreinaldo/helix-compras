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

<div class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        {{ $slot }}
    </div>
</div>

@livewireScripts
</body>
</html>
