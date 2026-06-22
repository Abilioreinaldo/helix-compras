{{--
    Fundo SVG inline da tela de autenticação (login / troca de senha).
    Tema "compras/logística" em flat design: carrinho, caixas empilhadas, gráfico de
    tendência e conectores — paleta emerald (#10b981) + azul (#1e40af) sobre gradiente
    escuro. Estático (sem JS/animação), responsivo (slice), sutil (camadas de opacidade).
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
        {{-- Gradiente de fundo (135°): slate-950 → quase preto --}}
        <linearGradient id="lb-bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#0f172a" />
            <stop offset="55%" stop-color="#0b1120" />
            <stop offset="100%" stop-color="#0a0a0a" />
        </linearGradient>

        {{-- Gradiente do gráfico: verde → azul --}}
        <linearGradient id="lb-chart" x1="0%" y1="100%" x2="0%" y2="0%">
            <stop offset="0%" stop-color="#10b981" />
            <stop offset="100%" stop-color="#1e40af" />
        </linearGradient>

        {{-- Brilho de marca suave (emerald) --}}
        <radialGradient id="lb-glow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="rgba(16,185,129,0.14)" />
            <stop offset="100%" stop-color="rgba(16,185,129,0)" />
        </radialGradient>

        {{-- Desfoque leve para suavizar os elementos --}}
        <filter id="lb-soft" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur in="SourceGraphic" stdDeviation="0.9" />
        </filter>
    </defs>

    {{-- Base --}}
    <rect width="1200" height="800" fill="url(#lb-bg)" />

    {{-- Brilhos de marca em cantos opostos --}}
    <circle cx="140" cy="90" r="360" fill="url(#lb-glow)" />
    <circle cx="1080" cy="740" r="380" fill="url(#lb-glow)" opacity="0.7" />

    {{-- Conectores sutis entre os elementos --}}
    <g opacity="0.12" filter="url(#lb-soft)">
        <path d="M 1010 200 Q 820 360 880 560" stroke="#ffffff" stroke-width="2" fill="none" stroke-dasharray="6,8" />
        <path d="M 220 600 Q 480 520 740 600" stroke="#ffffff" stroke-width="2" fill="none" stroke-dasharray="6,8" />
    </g>

    {{-- CARRINHO DE COMPRAS (canto superior direito) --}}
    <g opacity="0.4" filter="url(#lb-soft)" transform="translate(980, 70)">
        <g fill="none" stroke="#10b981" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
            {{-- Alça + cesto (trapézio) --}}
            <path d="M 4 6 H 28 L 44 92 H 150 L 168 34 H 52" />
            {{-- Divisórias do cesto --}}
            <path d="M 70 34 L 74 92 M 100 34 L 100 92 M 130 34 L 126 92" stroke-width="3" opacity="0.7" />
        </g>
        {{-- Itens dentro --}}
        <rect x="58" y="50" width="22" height="34" rx="3" fill="#1e40af" opacity="0.6" />
        <rect x="88" y="56" width="22" height="28" rx="3" fill="#10b981" opacity="0.5" />
        <rect x="118" y="48" width="22" height="36" rx="3" fill="#1e40af" opacity="0.5" />
        {{-- Rodas --}}
        <circle cx="64" cy="116" r="13" fill="#10b981" />
        <circle cx="136" cy="116" r="13" fill="#10b981" />
    </g>

    {{-- CAIXAS EMPILHADAS (canto inferior esquerdo, leve perspectiva) --}}
    <g opacity="0.35" filter="url(#lb-soft)">
        {{-- Caixa base --}}
        <g>
            <polygon points="60,640 160,640 178,622 78,622" fill="#1e40af" opacity="0.45" />
            <rect x="60" y="640" width="100" height="86" fill="#1e40af" opacity="0.6" />
            <rect x="60" y="640" width="100" height="86" fill="none" stroke="#10b981" stroke-width="2" />
            <line x1="110" y1="640" x2="110" y2="726" stroke="#10b981" stroke-width="2" opacity="0.6" />
        </g>
        {{-- Caixa meio --}}
        <g>
            <polygon points="86,566 178,566 194,550 102,550" fill="#1e40af" opacity="0.4" />
            <rect x="86" y="566" width="92" height="74" fill="#1e40af" opacity="0.5" />
            <rect x="86" y="566" width="92" height="74" fill="none" stroke="#10b981" stroke-width="2" />
            <line x1="132" y1="566" x2="132" y2="640" stroke="#10b981" stroke-width="2" opacity="0.6" />
        </g>
        {{-- Caixa topo --}}
        <g>
            <polygon points="108,500 192,500 206,486 122,486" fill="#1e40af" opacity="0.35" />
            <rect x="108" y="500" width="84" height="66" fill="#1e40af" opacity="0.45" />
            <rect x="108" y="500" width="84" height="66" fill="none" stroke="#10b981" stroke-width="2" />
            <line x1="150" y1="500" x2="150" y2="566" stroke="#10b981" stroke-width="2" opacity="0.6" />
        </g>
    </g>

    {{-- GRÁFICO DE TENDÊNCIA (centro-direita) --}}
    <g opacity="0.25" filter="url(#lb-soft)">
        {{-- Barras crescentes --}}
        <rect x="740" y="650" width="28" height="60" rx="3" fill="url(#lb-chart)" />
        <rect x="784" y="600" width="28" height="110" rx="3" fill="url(#lb-chart)" />
        <rect x="828" y="548" width="28" height="162" rx="3" fill="url(#lb-chart)" />
        <rect x="872" y="486" width="28" height="224" rx="3" fill="url(#lb-chart)" />
        {{-- Linha de tendência + seta --}}
        <path d="M 754 648 L 798 596 L 842 542 L 886 482" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M 886 482 l -20 4 m 20 -4 l -4 20" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    </g>
</svg>
