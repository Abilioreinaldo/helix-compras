{{--
    Fundo SVG inline da tela de autenticação (login / troca de senha).
    Estilo NEON DARK: gradiente slate-950 → azul (#1e40af) → rosa (#ec4899), com glows
    neon e padrão geométrico sutil. Estático (sem JS/animação), responsivo (slice).
--}}
<svg
    {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-0 h-full w-full select-none print:hidden']) }}
    viewBox="0 0 1200 800"
    preserveAspectRatio="xMidYMid slice"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
>
    <defs>
        {{-- Gradiente principal (135°): azul-escuro → azul → roxo --}}
        <linearGradient id="lb-bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#0f172a" />
            <stop offset="50%" stop-color="#1e3a8a" />
            <stop offset="100%" stop-color="#6d28d9" />
        </linearGradient>

        {{-- Glow neon azul --}}
        <radialGradient id="lb-glow-blue" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="rgba(59,130,246,0.35)" />
            <stop offset="100%" stop-color="rgba(59,130,246,0)" />
        </radialGradient>

        {{-- Glow neon violeta (accent HELIX) --}}
        <radialGradient id="lb-glow-cyan" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="rgba(168,85,247,0.32)" />
            <stop offset="100%" stop-color="rgba(168,85,247,0)" />
        </radialGradient>

        {{-- Padrão de grade neon sutil --}}
        <pattern id="lb-grid" width="48" height="48" patternUnits="userSpaceOnUse">
            <path d="M48 0H0V48" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1" />
        </pattern>
    </defs>

    {{-- Base --}}
    <rect width="1200" height="800" fill="url(#lb-bg)" />

    {{-- Grade neon (opacidade ~15%) --}}
    <rect width="1200" height="800" fill="url(#lb-grid)" opacity="0.6" />

    {{-- Glows neon nos cantos opostos --}}
    <circle cx="160" cy="120" r="420" fill="url(#lb-glow-blue)" />
    <circle cx="1060" cy="700" r="460" fill="url(#lb-glow-cyan)" />
    <circle cx="640" cy="420" r="260" fill="url(#lb-glow-blue)" opacity="0.5" />

    {{-- Linhas neon diagonais --}}
    <g stroke-width="1.5" fill="none">
        <line x1="0" y1="160" x2="1200" y2="620" stroke="rgba(59,130,246,0.15)" />
        <line x1="1200" y1="60" x2="0" y2="740" stroke="rgba(168,85,247,0.13)" />
    </g>

    {{-- Anéis neon (contornos) --}}
    <g fill="none">
        <circle cx="980" cy="180" r="90" stroke="rgba(168,85,247,0.2)" stroke-width="1.5" />
        <circle cx="220" cy="640" r="64" stroke="rgba(59,130,246,0.2)" stroke-width="1.5" />
        <rect x="60" y="120" width="200" height="200" rx="16" stroke="rgba(59,130,246,0.12)" stroke-width="1" />
    </g>
</svg>
