{{--
    Fundo SVG inline da tela de autenticação (login / troca de senha).
    Dark executivo (SaaS): gradiente slate-950 → black, glows verdes/azuis sutis,
    textura de pontos e acentos geométricos. Estático (sem JS/animação), responsivo
    via preserveAspectRatio="...slice". Merge de classes para sobrescrever quando preciso.
--}}
<svg
    {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-0 h-full w-full select-none']) }}
    viewBox="0 0 1200 800"
    preserveAspectRatio="xMidYMid slice"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
>
    <defs>
        {{-- Gradiente principal (135°): slate-950 → quase preto --}}
        <linearGradient id="lb-bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#0f172a" />
            <stop offset="55%" stop-color="#0b1120" />
            <stop offset="100%" stop-color="#0a0a0a" />
        </linearGradient>

        {{-- Brilho suave (emerald) --}}
        <radialGradient id="lb-glow-emerald" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="rgba(16,185,129,0.16)" />
            <stop offset="100%" stop-color="rgba(16,185,129,0)" />
        </radialGradient>

        {{-- Brilho suave (azul) --}}
        <radialGradient id="lb-glow-blue" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="rgba(59,130,246,0.12)" />
            <stop offset="100%" stop-color="rgba(59,130,246,0)" />
        </radialGradient>

        {{-- Textura de pontos sutil --}}
        <pattern id="lb-dots" width="38" height="38" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.3" fill="rgba(148,163,184,0.05)" />
        </pattern>

        {{-- Desfoque leve para os acentos --}}
        <filter id="lb-soft" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="0.6" />
        </filter>
    </defs>

    {{-- Base --}}
    <rect width="1200" height="800" fill="url(#lb-bg)" />

    {{-- Textura de pontos por cima da base --}}
    <rect width="1200" height="800" fill="url(#lb-dots)" />

    {{-- Glows de marca (cantos opostos) --}}
    <circle cx="120" cy="80" r="380" fill="url(#lb-glow-emerald)" />
    <circle cx="1080" cy="760" r="420" fill="url(#lb-glow-blue)" />
    <circle cx="640" cy="420" r="240" fill="url(#lb-glow-emerald)" opacity="0.5" />

    {{-- Linhas diagonais sutis --}}
    <g stroke-width="1.5" filter="url(#lb-soft)">
        <line x1="0" y1="120" x2="1200" y2="640" stroke="rgba(59,130,246,0.05)" />
        <line x1="0" y1="0" x2="1200" y2="800" stroke="rgba(16,185,129,0.04)" />
        <line x1="1200" y1="0" x2="0" y2="800" stroke="rgba(16,185,129,0.035)" />
    </g>

    {{-- Acentos geométricos (contornos finos) --}}
    <g fill="none" filter="url(#lb-soft)">
        <rect x="60" y="120" width="220" height="220" rx="14" stroke="rgba(16,185,129,0.07)" stroke-width="1" />
        <rect x="930" y="470" width="170" height="170" rx="12" stroke="rgba(59,130,246,0.06)" stroke-width="1" />
        <circle cx="980" cy="150" r="70" stroke="rgba(16,185,129,0.06)" stroke-width="1" />
        <circle cx="200" cy="640" r="54" stroke="rgba(59,130,246,0.05)" stroke-width="1" />
    </g>

    {{-- Vinheta inferior para dar profundidade ao card --}}
    <rect width="1200" height="800" fill="url(#lb-bg)" opacity="0.18" />
</svg>
