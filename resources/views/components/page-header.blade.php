@props([
    'title' => '',
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="flex items-center gap-4">
        @if($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-800 bg-slate-900 text-emerald-400">
                <x-icon :name="$icon" class="h-6 w-6" />
            </span>
        @endif
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">{{ $title }}</h1>
            @if($subtitle)
                <p class="mt-0.5 text-sm text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
