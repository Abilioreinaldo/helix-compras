@props([])

{{--
    Barra de filtros para telas de relatório (canvas dark).
    Use <x-filter-bar.field label="Ano"> ... controle ... </x-filter-bar.field> para cada filtro,
    ou coloque os controles direto no slot. Estilo de controle sugerido:
    class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100
           focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
--}}
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-800 bg-slate-900 p-4']) }}>
    {{ $slot }}
</div>
