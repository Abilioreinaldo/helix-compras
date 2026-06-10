<ul class="space-y-1 px-3">
    @foreach ($itens as $item)
        <li>
            <a
                href="{{ $item['href'] }}"
                class="flex items-center px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 transition-colors"
            >
                {{ $item['label'] }}
            </a>
        </li>
    @endforeach
</ul>
