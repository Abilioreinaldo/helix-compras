@props([])

{{--
    Barra de filtros para telas de relatório (canvas dark).
    Use <x-filter-bar.field label="Ano"> ... controle ... </x-filter-bar.field> para cada filtro,
    ou coloque os controles direto no slot. Estilo de controle sugerido:
    class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-slate-100
           focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
--}}
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-4']) }}>
    {{ $slot }}
</div>
