<div class="report-canvas">
    <x-page-header
        title="Gestão de Cotações"
        icon="document"
        :subtitle="$requisicao->codigo.' — '.($requisicao->unidade->nome ?? '—')"
    >
        <x-slot:actions>
            <a href="{{ route('compradora.mapa-cotacao', ['requisicaoId' => $requisicao->id]) }}"
                class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-zinc-700">
                Mapa comparativo
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Progresso das cotações --}}
    <x-report-card title="Progresso">
        <div class="flex flex-wrap items-center gap-8">
            <div class="text-center">
                <div class="text-2xl font-bold text-slate-100">{{ $cotacoes->count() }}</div>
                <div class="text-xs text-slate-500">Cotações registradas</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $cotacoes->count() >= $minimoNecessario ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $minimoNecessario }}
                </div>
                <div class="text-xs text-slate-500">Mínimo necessário</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $temVencedora ? 'text-emerald-400' : 'text-slate-500' }}">
                    {{ $temVencedora ? '✓' : '—' }}
                </div>
                <div class="text-xs text-slate-500">Vencedora definida</div>
            </div>
            @if ($requisicao->is_emergencial)
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-rose-500/15 text-rose-400">Emergencial</span>
            @endif
            <div class="ml-auto">
                @if ($podeConcluir)
                    <button wire:click="$set('mostrarModalConcluir', true)"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                        Concluir Cotação
                    </button>
                @else
                    <button disabled class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-medium text-slate-500 cursor-not-allowed">
                        Concluir Cotação
                    </button>
                @endif
            </div>
        </div>
        @error('cotacoes')
            <p class="mt-3 text-sm text-rose-400">{{ $message }}</p>
        @enderror
    </x-report-card>

    {{-- Solicitar cotação por e-mail (captura IMAP preenche a sugestão depois) --}}
    <x-report-card title="Solicitar cotação por e-mail" icon="truck" subtitle="Cria uma cotação aguardando e envia o pedido ao fornecedor. A resposta é capturada automaticamente.">
        @error('fornecedoresSolicitar')
            <div class="mb-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-300">{{ $message }}</div>
        @enderror
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium text-slate-300">Fornecedores</label>
                <select multiple wire:model="fornecedoresSolicitar" size="4" class="input-dark w-full">
                    @foreach ($fornecedores as $f)
                        <option value="{{ $f->id }}" @disabled(! $f->contato_email)>
                            {{ $f->nome_fantasia }}{{ $f->contato_email ? '' : ' (sem e-mail)' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Segure Ctrl/Cmd para selecionar vários.</p>
            </div>
            <button wire:click="solicitarPorEmail" wire:loading.attr="disabled"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                Enviar solicitação
            </button>
        </div>
    </x-report-card>

    {{-- Lista de cotações --}}
    <x-report-card padding="p-0">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
            <h2 class="text-sm font-semibold text-slate-200">Cotações Recebidas</h2>
            <button wire:click="$toggle('mostrarFormulario')"
                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500">
                + Nova Cotação
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Fornecedor</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Valor</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Prazo (dias)</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Validade proposta</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Arquivo</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Registrada por</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($cotacoes as $cotacao)
                        <tr class="transition-colors hover:bg-zinc-800/40 {{ $cotacao->vencedora ? 'bg-emerald-500/5' : '' }}">
                            <td class="px-4 py-3 text-slate-200">
                                {{ $cotacao->fornecedor->nome_fantasia ?? '—' }}
                                @if ($cotacao->vencedora)
                                    <span class="ml-2 inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-emerald-500/15 text-emerald-400">Vencedora</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-200">
                                @if ($cotacao->valor !== null)
                                    R$ {{ number_format((float) $cotacao->valor, 2, ',', '.') }}
                                @elseif ($cotacao->valor_respondido !== null)
                                    <span class="font-normal text-slate-500" @if($cotacao->observacoes_fornecedor) title="{{ \Illuminate\Support\Str::limit($cotacao->observacoes_fornecedor, 300) }}" @endif>
                                        Sugerido: R$ {{ number_format((float) $cotacao->valor_respondido, 2, ',', '.') }}
                                    </span>
                                @else
                                    <span class="font-normal italic text-slate-500">Aguardando resposta</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                @if ($cotacao->valor !== null)
                                    {{ $cotacao->prazo_entrega_dias ? $cotacao->prazo_entrega_dias.' dias' : '—' }}
                                @elseif ($cotacao->prazo_respondido !== null)
                                    <span class="text-slate-500">Sugerido: {{ $cotacao->prazo_respondido }} dias</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                @if ($cotacao->validade_proposta)
                                    @php $vencida = $cotacao->validade_proposta->lt(\Illuminate\Support\Carbon::today()); @endphp
                                    <span class="{{ $vencida ? 'text-rose-400 font-medium' : '' }}">{{ $cotacao->validade_proposta->format('d/m/Y') }}</span>
                                    @if ($vencida)<span class="block text-xs text-rose-400">vencida</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($cotacao->arquivo_path)
                                    <a href="{{ route('compradora.cotacoes.arquivo', $cotacao) }}" target="_blank"
                                        class="text-xs text-emerald-400 hover:text-emerald-300">
                                        {{ $cotacao->arquivo_nome_original ?? 'Ver arquivo' }}
                                    </a>
                                @else
                                    <span class="text-xs text-slate-500">Sem arquivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ $cotacao->criador->name ?? '—' }}
                                <span class="block text-xs text-slate-500">{{ $cotacao->created_at?->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($cotacao->valor === null && $cotacao->valor_respondido !== null)
                                    <button wire:click="confirmarSugestao({{ $cotacao->id }})"
                                        class="text-xs font-medium text-emerald-400 hover:text-emerald-300">
                                        Confirmar sugestão
                                    </button>
                                @elseif ($cotacao->valor !== null && ! $cotacao->vencedora)
                                    <button wire:click="marcarVencedora({{ $cotacao->id }})"
                                        class="text-xs text-emerald-400 hover:text-emerald-300">
                                        Marcar vencedora
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
                                Nenhuma cotação registrada ainda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-report-card>

    {{-- Formulário nova cotação --}}
    @if ($mostrarFormulario)
        <x-report-card title="Nova Cotação">
            @error('formulario')
                <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</div>
            @enderror

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Fornecedor <span class="text-rose-400">*</span></label>
                    <select wire:model="fornecedorId"
                        class="input-dark w-full @error('fornecedorId') border-rose-500 @enderror">
                        <option value="">Selecione...</option>
                        @foreach ($fornecedores as $f)
                            <option value="{{ $f->id }}">{{ $f->nome_fantasia }}</option>
                        @endforeach
                    </select>
                    @error('fornecedorId') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                @if ($requisicao->itens->isNotEmpty())
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-300 mb-1">Preço por item <span class="text-rose-400">*</span></label>
                        <div class="overflow-x-auto rounded-lg border border-zinc-800">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-800 bg-zinc-950/40">
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Qtd</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Preço unitário (R$)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800">
                                    @foreach ($requisicao->itens as $item)
                                        <tr>
                                            <td class="px-3 py-2 text-slate-300">{{ $item->descricao }}</td>
                                            <td class="px-3 py-2 text-right text-slate-400">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }} {{ $item->unidade_medida }}</td>
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" step="0.01" min="0" wire:model="precos.{{ $item->id }}"
                                                    class="input-dark w-32 text-right @error('precos.'.$item->id) border-rose-500 @enderror"
                                                    placeholder="0,00">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">O total é a soma (preço × quantidade). Itens em branco são ignorados.</p>
                    </div>
                @else
                    {{-- Fallback: requisição sem itens cadastrados → valor total (caminho legado). --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Valor total (R$) <span class="text-rose-400">*</span></label>
                        <input type="number" step="0.01" min="0" wire:model="valor"
                            class="input-dark w-full @error('valor') border-rose-500 @enderror"
                            placeholder="0,00">
                        @error('valor') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Prazo de entrega (dias)</label>
                    <input type="number" min="1" wire:model="prazoEntregaDias"
                        class="input-dark w-full"
                        placeholder="Ex: 7">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Validade da proposta</label>
                    <input type="date" wire:model="validadeProposta"
                        class="input-dark w-full">
                    @error('validadeProposta') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Anexo (PDF, JPG, PNG — máx 10 MB)</label>
                    <input type="file" wire:model="arquivo" accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full text-sm text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-slate-200 hover:file:bg-zinc-700">
                    <div wire:loading wire:target="arquivo" class="mt-1 text-xs text-slate-400">Enviando arquivo...</div>
                    @error('arquivo') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-1">Observações</label>
                    <textarea wire:model="observacoes" rows="2"
                        class="input-dark w-full"
                        placeholder="Condições comerciais, validade, etc."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <button wire:click="$set('mostrarFormulario', false)"
                    class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700">
                    Cancelar
                </button>
                <button wire:click="registrarCotacao"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Registrar Cotação
                </button>
            </div>
        </x-report-card>
    @endif

    {{-- Modal confirmação conclusão --}}
    @if ($mostrarModalConcluir)
        <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-slate-100 mb-2">Concluir Cotação</h2>
                <p class="text-sm text-slate-400 mb-4">
                    Confirma a conclusão da etapa de cotação e o avanço da requisição para aprovação?
                    Após confirmação, nenhuma nova cotação poderá ser adicionada.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('mostrarModalConcluir', false)"
                        class="rounded-lg bg-zinc-800 border border-zinc-700 px-4 py-2 text-sm text-slate-200 hover:bg-zinc-700">
                        Cancelar
                    </button>
                    <button wire:click="concluirCotacao"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                        Confirmar Conclusão
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
