<x-layouts.guest>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="rounded-xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <h1 class="text-xl font-bold text-slate-100">Proposta de cotação</h1>
            <p class="mt-1 text-sm text-slate-400">
                {{ $cotacao->fornecedor?->nome ?? $cotacao->fornecedor?->razao_social }}
                — requisição {{ $cotacao->requisicao?->codigo ?? '—' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Link pessoal e de uso único, válido até {{ $expiraEm->format('d/m/Y H:i') }}. Após o envio a proposta não pode ser alterada por este link.
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6 space-y-5">
                @csrf

                @if ($itens->isNotEmpty())
                    <div class="overflow-x-auto rounded-lg border border-slate-800">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-800 bg-slate-950/40">
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500">Item</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase text-slate-500">Qtd</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase text-slate-500">Preço unitário (R$)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach ($itens as $item)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-300">{{ $item->descricao }}</td>
                                        <td class="px-3 py-2 text-right text-slate-400">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }} {{ $item->unidade_medida }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" step="0.01" min="0" name="precos[{{ $item->id }}]" value="{{ old('precos.'.$item->id) }}"
                                                class="input-dark w-32 text-right" placeholder="0,00">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-500">Deixe em branco os itens que não pode atender.</p>
                @else
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Valor total (R$)</label>
                        <input type="number" step="0.01" min="0" name="valor" value="{{ old('valor') }}" class="input-dark w-full" required>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Prazo de entrega (dias)</label>
                        <input type="number" min="1" max="365" name="prazo_entrega_dias" value="{{ old('prazo_entrega_dias') }}" class="input-dark w-full">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Validade da proposta</label>
                        <input type="date" name="validade_proposta" value="{{ old('validade_proposta') }}" class="input-dark w-full">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Observações</label>
                    <textarea name="observacoes" rows="3" maxlength="2000" class="input-dark w-full">{{ old('observacoes') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                        Enviar proposta
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.guest>
