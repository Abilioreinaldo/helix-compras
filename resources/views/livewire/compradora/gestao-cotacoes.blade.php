<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Gestão de Cotações</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $requisicao->codigo }} — {{ $requisicao->unidade->nome ?? '—' }}
                @if ($requisicao->is_emergencial)
                    <span class="ml-2 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Emergencial</span>
                @endif
            </p>
        </div>
        <a href="{{ route('requisicoes.detalhe', $requisicao->id) }}" class="text-sm text-gray-600 hover:text-gray-800">
            ← Voltar à Requisição
        </a>
    </div>

    {{-- Progresso das cotações --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex items-center gap-6">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-800">{{ $cotacoes->count() }}</div>
                <div class="text-xs text-gray-500">Cotações registradas</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $cotacoes->count() >= $minimoNecessario ? 'text-green-600' : 'text-red-500' }}">
                    {{ $minimoNecessario }}
                </div>
                <div class="text-xs text-gray-500">Mínimo necessário</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $temVencedora ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $temVencedora ? '✓' : '—' }}
                </div>
                <div class="text-xs text-gray-500">Vencedora definida</div>
            </div>
            <div class="ml-auto">
                @if ($podeConcluir)
                    <button wire:click="$set('mostrarModalConcluir', true)"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Concluir Cotação
                    </button>
                @else
                    <button disabled class="bg-gray-200 text-gray-400 text-sm font-medium px-4 py-2 rounded-md cursor-not-allowed">
                        Concluir Cotação
                    </button>
                @endif
            </div>
        </div>
        @error('cotacoes')
            <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Lista de cotações --}}
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Cotações Recebidas</h2>
            <button wire:click="$toggle('mostrarFormulario')"
                class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md">
                + Nova Cotação
            </button>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prazo (dias)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arquivo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registrada por</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($cotacoes as $cotacao)
                    <tr class="hover:bg-gray-50 {{ $cotacao->vencedora ? 'bg-green-50' : '' }}">
                        <td class="px-4 py-3 text-sm text-gray-800">
                            {{ $cotacao->fornecedor->nome_fantasia ?? '—' }}
                            @if ($cotacao->vencedora)
                                <span class="ml-2 inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Vencedora</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800 font-medium">
                            R$ {{ number_format((float) $cotacao->valor, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $cotacao->prazo_entrega_dias ? $cotacao->prazo_entrega_dias.' dias' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($cotacao->arquivo_path)
                                <a href="{{ route('compradora.cotacoes.arquivo', $cotacao) }}" target="_blank"
                                    class="text-blue-600 hover:text-blue-800 text-xs">
                                    {{ $cotacao->arquivo_nome_original ?? 'Ver arquivo' }}
                                </a>
                            @else
                                <span class="text-gray-400 text-xs">Sem arquivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $cotacao->criador->name ?? '—' }}
                            <span class="block text-xs text-gray-400">{{ $cotacao->created_at?->format('d/m/Y H:i') }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            @unless ($cotacao->vencedora)
                                <button wire:click="marcarVencedora({{ $cotacao->id }})"
                                    class="text-green-600 hover:text-green-800 text-xs">
                                    Marcar vencedora
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                            Nenhuma cotação registrada ainda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Formulário nova cotação --}}
    @if ($mostrarFormulario)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Nova Cotação</h2>

            @error('formulario')
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-600">{{ $message }}</div>
            @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fornecedor <span class="text-red-500">*</span></label>
                    <select wire:model="fornecedorId"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('fornecedorId') border-red-500 @enderror">
                        <option value="">Selecione...</option>
                        @foreach ($fornecedores as $f)
                            <option value="{{ $f->id }}">{{ $f->nome_fantasia }}</option>
                        @endforeach
                    </select>
                    @error('fornecedorId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor (R$) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" wire:model="valor"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('valor') border-red-500 @enderror"
                        placeholder="0,00">
                    @error('valor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prazo de entrega (dias)</label>
                    <input type="number" min="1" wire:model="prazoEntregaDias"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ex: 7">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anexo (PDF, JPG, PNG — máx 10 MB)</label>
                    <input type="file" wire:model="arquivo" accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    @error('arquivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                    <textarea wire:model="observacoes" rows="2"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Condições comerciais, validade, etc."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <button wire:click="$set('mostrarFormulario', false)"
                    class="text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50">
                    Cancelar
                </button>
                <button wire:click="registrarCotacao"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                    Registrar Cotação
                </button>
            </div>
        </div>
    @endif

    {{-- Modal confirmação conclusão --}}
    @if ($mostrarModalConcluir)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-2">Concluir Cotação</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Confirma a conclusão da etapa de cotação e o avanço da requisição para aprovação?
                    Após confirmação, nenhuma nova cotação poderá ser adicionada.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('mostrarModalConcluir', false)"
                        class="text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="concluirCotacao"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Confirmar Conclusão
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
