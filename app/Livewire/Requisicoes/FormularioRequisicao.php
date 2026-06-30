<?php

namespace App\Livewire\Requisicoes;

use App\Actions\SubmeterRequisicaoAction;
use App\Actions\TransicionarStatusRequisicaoAction;
use App\Enums\StatusRequisicao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Obra;
use App\Models\Requisicao;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormularioRequisicao extends Component
{
    public ?int $requisicaoId = null;

    // Campos da requisição
    public ?int $unidadeId = null;

    public ?int $centroCustoId = null;

    public ?int $obraId = null;

    public bool $urgente = false;

    public bool $isEmergencial = false;

    public string $justificativa = '';

    public string $motivoCancelamento = '';

    public bool $mostrarModalCancelar = false;

    // Busca server-side do catálogo (Sec P2-02)
    public string $buscaCatalogo = '';

    // Itens
    /** @var array<int, array{descricao: string, quantidade: string, unidade_medida: string, valor_unitario_estimado: string, item_catalogo_id: ?int, avulso: bool}> */
    public array $itens = [];

    // Verba
    public ?float $percentualVerba = null;

    public bool $alertaVerba = false;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($id);
            abort_unless($requisicao->status->permiteEdicao(), 403);

            $this->requisicaoId = $requisicao->id;
            $this->unidadeId = $requisicao->unidade_id;
            $this->centroCustoId = $requisicao->centro_custo_id;
            $this->obraId = $requisicao->obra_id;
            $this->urgente = $requisicao->urgente;
            $this->isEmergencial = $requisicao->is_emergencial;
            $this->justificativa = $requisicao->justificativa ?? '';
            $this->itens = $requisicao->itens->map(fn ($item) => [
                'descricao' => $item->descricao,
                'quantidade' => (string) $item->quantidade,
                'unidade_medida' => $item->unidade_medida ?? '',
                'valor_unitario_estimado' => $item->valor_unitario_estimado !== null ? (string) $item->valor_unitario_estimado : '',
                'item_catalogo_id' => $item->item_catalogo_id,
                'avulso' => $item->avulso,
            ])->toArray();
        } else {
            $this->unidadeId = $this->resolverUnidadeInicial();
            $this->itens = $this->montarItensIniciais();
        }
    }

    // ─── Helpers de inicialização ─────────────────────────────────────────────

    /**
     * Resolve o ID de unidade inicial para novos rascunhos.
     * Aceita query param 'unidade_id' somente se o usuário a enxerga (via UnidadeScope ou pivot).
     * Caso contrário, retorna a primeira unidade do usuário.
     */
    private function resolverUnidadeInicial(): ?int
    {
        $usuario = auth()->user();
        $unidadeIdQuery = request()->integer('unidade_id') ?: null;

        if ($unidadeIdQuery) {
            // Verifica se o usuário vê essa unidade
            $visivel = $usuario->podeVerTodasUnidades()
                ? Unidade::withoutGlobalScopes()->where('id', $unidadeIdQuery)->whereNull('deleted_at')->exists()
                : $usuario->unidades()->withoutGlobalScopes()->where('unidades.id', $unidadeIdQuery)->exists();

            if ($visivel) {
                return $unidadeIdQuery;
            }
        }

        // Default: primeira unidade do usuário
        return $usuario->unidades()->withoutGlobalScopes()->first()?->id;
    }

    /**
     * Monta a lista de itens inicial para novos rascunhos.
     * Se vier query param 'item_catalogo_id' válido (ativo, não deletado), pré-preenche como item de catálogo.
     * Caso contrário, retorna o item avulso vazio padrão.
     *
     * @return array<int, array{descricao: string, quantidade: string, unidade_medida: string, valor_unitario_estimado: string, item_catalogo_id: ?int, avulso: bool}>
     */
    private function montarItensIniciais(): array
    {
        $itemCatalogoId = request()->integer('item_catalogo_id') ?: null;
        $quantidadeSugerida = (float) request()->get('quantidade_sugerida', 1);

        if ($itemCatalogoId) {
            $catalogoItem = CatalogoItem::where('ativo', true)->find($itemCatalogoId);

            if ($catalogoItem) {
                $homologado = $catalogoItem->precoHomologadoValido();

                return [[
                    'descricao' => $catalogoItem->descricao,
                    'quantidade' => (string) max(1.0, $quantidadeSugerida),
                    'unidade_medida' => $catalogoItem->unidade_medida ?? 'un',
                    'valor_unitario_estimado' => $homologado ? (string) $homologado->preco : '',
                    'item_catalogo_id' => $catalogoItem->id,
                    'avulso' => false,
                ]];
            }
        }

        // Default: item avulso vazio
        return [['descricao' => '', 'quantidade' => '1', 'unidade_medida' => 'un', 'valor_unitario_estimado' => '', 'item_catalogo_id' => null, 'avulso' => true]];
    }

    public function updatedObraId(): void
    {
        $this->recalcularVerba();
    }

    public function adicionarItem(): void
    {
        $this->itens[] = ['descricao' => '', 'quantidade' => '1', 'unidade_medida' => 'un', 'valor_unitario_estimado' => '', 'item_catalogo_id' => null, 'avulso' => true];
    }

    public function removerItem(int $indice): void
    {
        array_splice($this->itens, $indice, 1);
        $this->itens = array_values($this->itens);
        $this->recalcularVerba();
    }

    /**
     * Vincula um item de catálogo ao item do formulário: marca como não avulso,
     * preenche descrição/unidade a partir do catálogo e guarda o id selecionado.
     */
    public function selecionarItemCatalogo(int $indice, ?int $itemCatalogoId): void
    {
        if ($itemCatalogoId === null) {
            $this->itens[$indice]['item_catalogo_id'] = null;
            $this->itens[$indice]['avulso'] = true;

            return;
        }

        $catalogoItem = CatalogoItem::where('ativo', true)->find($itemCatalogoId);
        if (! $catalogoItem) {
            return;
        }

        $this->itens[$indice]['item_catalogo_id'] = $catalogoItem->id;
        $this->itens[$indice]['avulso'] = false;
        $this->itens[$indice]['descricao'] = $catalogoItem->descricao;
        $this->itens[$indice]['unidade_medida'] = $catalogoItem->unidade_medida ?? $this->itens[$indice]['unidade_medida'];

        // Autofill do preço estimado a partir do preço homologado válido (se houver).
        $homologado = $catalogoItem->precoHomologadoValido();
        if ($homologado) {
            $this->itens[$indice]['valor_unitario_estimado'] = (string) $homologado->preco;
        }

        $this->recalcularVerba();
    }

    /**
     * Pré-visualização (na tela, antes de submeter) de que a requisição seguirá
     * pela via expressa: todos os itens atuais são de catálogo com preço
     * homologado válido do mesmo fornecedor. Espelha Requisicao::avaliarViaExpressa.
     */
    public function previewExpressa(): bool
    {
        if (empty($this->itens)) {
            return false;
        }

        $fornecedorId = null;

        foreach ($this->itens as $item) {
            $catalogoId = $item['item_catalogo_id'] ?? null;

            if (($item['avulso'] ?? true) || ! $catalogoId) {
                return false;
            }

            $homologado = CatalogoItem::find($catalogoId)?->precoHomologadoValido();

            if (! $homologado) {
                return false;
            }

            if ($fornecedorId === null) {
                $fornecedorId = $homologado->fornecedor_id;
            } elseif ($fornecedorId !== $homologado->fornecedor_id) {
                return false;
            }
        }

        return true;
    }

    public function updatedItens(): void
    {
        $this->recalcularVerba();
    }

    private function recalcularVerba(): void
    {
        $this->percentualVerba = null;
        $this->alertaVerba = false;

        if (! $this->obraId) {
            return;
        }

        $obra = Obra::withoutGlobalScopes()->find($this->obraId);
        if (! $obra || ! $obra->verba) {
            return;
        }

        $valorAtual = collect($this->itens)->sum(fn ($item) => (float) ($item['quantidade'] ?? 0) * (float) ($item['valor_unitario_estimado'] ?? 0)
        );

        $excludeId = $this->requisicaoId ?? 0;
        $idsComprometidos = Requisicao::withoutGlobalScopes()
            ->where('obra_id', $this->obraId)
            ->whereNotIn('status', [StatusRequisicao::Rascunho->value, StatusRequisicao::Cancelada->value, StatusRequisicao::Devolvida->value])
            ->where('id', '!=', $excludeId)
            ->pluck('id');

        $verbaConsumida = DB::table('requisicao_itens')
            ->whereIn('requisicao_id', $idsComprometidos)
            ->sum(DB::raw('COALESCE(quantidade * valor_unitario_estimado, 0)'));

        $percentual = ($verbaConsumida + $valorAtual) / (float) $obra->verba * 100;
        $this->percentualVerba = round($percentual, 1);
        $this->alertaVerba = $percentual >= 80;
    }

    private function regrasValidacao(): array
    {
        $rules = [
            'unidadeId' => 'required|exists:unidades,id',
            'centroCustoId' => 'required|exists:centros_custo,id',
            'obraId' => 'nullable|exists:obras,id',
            'urgente' => 'boolean',
            'isEmergencial' => 'boolean',
            'justificativa' => $this->isEmergencial ? 'required|string|min:10' : 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.descricao' => 'required|string|max:255',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
            'itens.*.unidade_medida' => 'nullable|string|max:10',
            'itens.*.valor_unitario_estimado' => 'nullable|numeric|min:0',
            'itens.*.avulso' => 'boolean',
            'itens.*.item_catalogo_id' => [
                'nullable',
                Rule::exists('catalogo_itens', 'id')->whereNull('deleted_at')->where('ativo', true),
                function (string $attribute, mixed $value, callable $fail) {
                    preg_match('/^itens\.(\d+)\./', $attribute, $matches);
                    $indice = (int) ($matches[1] ?? 0);
                    $avulso = (bool) ($this->itens[$indice]['avulso'] ?? true);

                    if (! $avulso && $value === null) {
                        $fail('Selecione um item do catálogo ou marque o item como avulso.');
                    }
                },
            ],
        ];

        return $rules;
    }

    public function salvar(): void
    {
        $this->validate($this->regrasValidacao(), [
            'unidadeId.required' => 'A unidade é obrigatória.',
            'centroCustoId.required' => 'O centro de custo é obrigatório.',
            'justificativa.required' => 'A justificativa é obrigatória para compras emergenciais.',
            'itens.required' => 'Adicione ao menos um item.',
            'itens.min' => 'Adicione ao menos um item.',
            'itens.*.descricao.required' => 'A descrição do item é obrigatória.',
            'itens.*.quantidade.required' => 'A quantidade é obrigatória.',
        ]);

        if ($this->requisicaoId) {
            $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($this->requisicaoId);
        } else {
            $requisicao = new Requisicao;
            $requisicao->solicitante_id = auth()->id();
        }

        $requisicao->fill([
            'unidade_id' => $this->unidadeId,
            'centro_custo_id' => $this->centroCustoId,
            'obra_id' => $this->obraId,
            'urgente' => $this->urgente,
            'is_emergencial' => $this->isEmergencial,
            'justificativa' => $this->isEmergencial ? $this->justificativa : null,
            'status' => StatusRequisicao::Rascunho,
        ]);
        $requisicao->save();

        $requisicao->itens()->delete();
        foreach ($this->itens as $item) {
            $requisicao->itens()->create([
                'descricao' => $item['descricao'],
                'quantidade' => (float) $item['quantidade'],
                'unidade_medida' => $item['unidade_medida'] ?: null,
                'valor_unitario_estimado' => $item['valor_unitario_estimado'] !== '' ? (float) $item['valor_unitario_estimado'] : null,
                'item_catalogo_id' => $item['item_catalogo_id'] ?? null,
                'avulso' => $item['avulso'] ?? true,
            ]);
        }

        $this->requisicaoId = $requisicao->id;
        $this->dispatch('notify', mensagem: 'Rascunho salvo com sucesso.');
    }

    public function submeter(): void
    {
        $this->validate($this->regrasValidacao(), [
            'unidadeId.required' => 'A unidade é obrigatória.',
            'centroCustoId.required' => 'O centro de custo é obrigatório.',
            'justificativa.required' => 'A justificativa é obrigatória para compras emergenciais.',
            'itens.required' => 'Adicione ao menos um item.',
        ]);

        $this->salvar();

        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($this->requisicaoId);

        try {
            $resultado = app(SubmeterRequisicaoAction::class)->execute($requisicao);
        } catch (ValidationException $e) {
            $this->addError('formulario', $e->getMessage());

            return;
        }

        $this->redirect(route('requisicoes.detalhe', $this->requisicaoId));
    }

    public function abrirModalCancelar(): void
    {
        $this->mostrarModalCancelar = true;
    }

    public function cancelarRequisicao(): void
    {
        if (! $this->requisicaoId) {
            $this->mostrarModalCancelar = false;

            return;
        }

        $this->validate(['motivoCancelamento' => 'required|string|min:5'], [
            'motivoCancelamento.required' => 'Informe o motivo do cancelamento.',
        ]);

        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($this->requisicaoId);
        $requisicao->update(['motivo_cancelamento' => $this->motivoCancelamento]);

        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::Cancelada);

        $this->redirect(route('requisicoes.index'));
    }

    public function render(): View
    {
        $unidades = auth()->user()->podeVerTodasUnidades()
            ? Unidade::withoutGlobalScopes()->where('status', 'ativa')->orderBy('nome')->get()
            : auth()->user()->unidades()->withoutGlobalScopes()->where('status', 'ativa')->get();

        $centrosCusto = $this->unidadeId
            ? CentroCusto::withoutGlobalScopes()->where('unidade_id', $this->unidadeId)->where('ativo', true)->orderBy('nome')->get()
            : collect();

        $obras = $this->unidadeId
            ? Obra::withoutGlobalScopes()->where('unidade_id', $this->unidadeId)->where('status', 'ativa')->orderBy('id')->get()
            : collect();

        // Sec P2-02: busca server-side paginada — carrega até 50 itens por vez, filtrando
        // pela busca digitada. A validação (Rule::exists) é independente desta lista e
        // valida diretamente no banco, garantindo que itens fora da página ainda passem.
        $queryItensCatalogo = CatalogoItem::where('ativo', true)->orderBy('descricao');
        if ($this->buscaCatalogo !== '') {
            $queryItensCatalogo->where('descricao', 'like', '%'.$this->buscaCatalogo.'%');
        }
        $itensCatalogo = $queryItensCatalogo->limit(50)->get();

        return view('livewire.requisicoes.formulario-requisicao', compact('unidades', 'centrosCusto', 'obras', 'itensCatalogo'))
            ->layout('components.layouts.app');
    }
}
