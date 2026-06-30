@props([
    'href' => '#',
    'icon' => 'dot',
    'active' => false,
])

<a
    href="{{ $href }}"
    @class([
        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
        'bg-slate-800 text-white shadow-sm' => $active,
        'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100' => ! $active,
    ])
    @if($active) aria-current="page" @endif
>
    <span @class(['absolute left-0 h-6 w-1 rounded-r-full bg-blue-400' => $active, 'sr-only' => ! $active])></span>
    <x-icon :name="$icon" @class([
        'h-5 w-5 shrink-0 transition-colors',
        'text-blue-400' => $active,
        'text-slate-500 group-hover:text-slate-300' => ! $active,
    ]) />
    <span class="truncate">{{ $slot }}</span>
</a>
