@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'padding' => 'p-5',
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-800 bg-slate-900 shadow-sm']) }}>
    @if($title || isset($actions))
        <div class="flex items-center justify-between gap-4 border-b border-slate-800 px-5 py-4">
            <div class="flex items-center gap-3">
                @if($icon)
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800 text-emerald-400">
                        <x-icon :name="$icon" class="h-4 w-4" />
                    </span>
                @endif
                <div>
                    @if($title)<h3 class="text-sm font-semibold text-slate-100">{{ $title }}</h3>@endif
                    @if($subtitle)<p class="text-xs text-slate-400">{{ $subtitle }}</p>@endif
                </div>
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>
