@props([
    'label' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1']) }}>
    @if($label)
        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</label>
    @endif
    {{ $slot }}
</div>
