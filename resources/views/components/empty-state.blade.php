@props([
    'icon' => 'inbox',
    'title' => 'Nada por aqui ainda',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-6 py-16 text-center']) }}>
    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-800 text-slate-400">
        <x-icon :name="$icon" class="h-7 w-7" />
    </div>
    <h3 class="mt-4 text-base font-semibold text-slate-200">{{ $title }}</h3>
    @if($message)
        <p class="mt-1 max-w-md text-sm text-slate-400">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
