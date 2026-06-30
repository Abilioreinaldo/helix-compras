@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'hint' => null,
    'accent' => 'emerald', // emerald | amber | sky | rose | slate
])

@php
    $accents = [
        'emerald' => 'bg-emerald-500/10 text-emerald-400 ring-blue-500/20',
        'amber' => 'bg-amber-500/10 text-amber-400 ring-amber-500/20',
        'sky' => 'bg-sky-500/10 text-sky-400 ring-sky-500/20',
        'rose' => 'bg-rose-500/10 text-rose-400 ring-rose-500/20',
        'slate' => 'bg-slate-500/10 text-slate-300 ring-slate-500/20',
    ];
    $accentClass = $accents[$accent] ?? $accents['emerald'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-800 bg-slate-900 p-5 transition-colors hover:border-slate-700']) }}>
    <div class="flex items-start justify-between gap-3">
        <span class="text-sm font-medium text-slate-400">{{ $label }}</span>
        @if($icon)
            <span class="flex h-9 w-9 items-center justify-center rounded-lg ring-1 {{ $accentClass }}">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
    <div class="mt-3 text-2xl font-bold tracking-tight text-white">{{ $value }}</div>
    @if($hint)
        <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
