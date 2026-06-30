@props([
    'class' => 'h-9 w-9',
    'variant' => 'gradient', // gradient | white | current
])

@php
    // Id único por instância — evita que múltiplos logos na mesma página
    // resolvam o gradiente para o primeiro <defs> (que pode estar oculto).
    $gid = 'helix-grad-'.uniqid();
    $stroke = match ($variant) {
        'white' => '#FFFFFF',
        'current' => 'currentColor',
        default => 'url(#'.$gid.')',
    };
@endphp

{{-- Símbolo HELIX oficial: duas fitas em hélice (DNA) formando o "X".
     Paths e gradiente do brand pack (#2563EB → #4F6BFF → #A855F7). --}}
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="212 102 600 600" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="HELIX">
    @if ($variant === 'gradient')
        <defs>
            <linearGradient id="{{ $gid }}" x1="20%" y1="10%" x2="85%" y2="90%">
                <stop offset="0%" stop-color="#2563EB" />
                <stop offset="52%" stop-color="#4F6BFF" />
                <stop offset="100%" stop-color="#A855F7" />
            </linearGradient>
        </defs>
    @endif
    <g fill="none" stroke="{{ $stroke }}" stroke-width="76" stroke-linecap="butt" stroke-linejoin="round">
        <path d="M 315 120 C 322 230 407 291 512 346 C 638 412 715 520 728 684" />
        <path d="M 709 120 C 702 230 617 291 512 346 C 386 412 309 520 296 684" />
    </g>
</svg>
