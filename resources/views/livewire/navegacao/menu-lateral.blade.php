<div class="space-y-6">
    @foreach ($grupos as $titulo => $itens)
        <div>
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $titulo }}</p>
            <ul class="space-y-1">
                @foreach ($itens as $item)
                    <li class="relative">
                        <x-sidebar-item
                            :href="$item['href']"
                            :icon="$item['icon']"
                            :active="request()->routeIs($item['route'])"
                        >
                            {{ $item['label'] }}
                        </x-sidebar-item>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
