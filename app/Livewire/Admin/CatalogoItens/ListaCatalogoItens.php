<?php

namespace App\Livewire\Admin\CatalogoItens;

use App\Actions\DefinirEstoqueMinimoAction;
use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ListaCatalogoItens extends Component
{
    use WithPagination;

    public string $busca = '';

    public string $filtroAtivo = '';

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    // Campos do formulário de item
    public string $descricao = '';

    public string $codigo = '';

    public string $unidadeMedida = '';

    public string $categoria = '';

    public bool $ativo = true;

    // ─── Modal: mínimos por unidade ──────────────────────────────────────────

    public bool $mostrarModalMinimos = false;

    public ?int $minimoItemId = null;

    public string $minimoItemDescricao = '';

    /**
     * @var array<int, array{unidade_id: int, nome: string, quantidade_minima: string}>
     */
    public array $minimosPorUnidade = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
    }

    public function abrirCriar(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->descricao = '';
        $this->codigo = '';
        $this->unidadeMedida = '';
        $this->categoria = '';
        $this->ativo = true;
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $this->resetValidation();
        $item = CatalogoItem::findOrFail($id);
        $this->editandoId = $id;
        $this->descricao = $item->descricao;
        $this->codigo = $item->codigo ?? '';
        $this->unidadeMedida = $item->unidade_medida ?? '';
        $this->categoria = $item->categoria ?? '';
        $this->ativo = $item->ativo;
        $this->mostrarModal = true;
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $codigoUnico = Rule::unique('catalogo_itens', 'codigo')
            ->whereNull('deleted_at')
            ->when($this->editandoId, fn ($rule) => $rule->ignore($this->editandoId));

        $this->validate([
            'descricao' => 'required|string|max:500',
            'codigo' => ['nullable', 'string', 'max:255', $codigoUnico],
            'unidadeMedida' => 'nullable|string|max:20',
            'categoria' => 'nullable|string|max:255',
            'ativo' => 'boolean',
        ], [
            'descricao.required' => 'A descrição é obrigatória.',
            'codigo.unique' => 'Este código já está cadastrado.',
        ]);

        $dados = [
            'descricao' => $this->descricao,
            'codigo' => $this->codigo ?: null,
            'unidade_medida' => $this->unidadeMedida ?: null,
            'categoria' => $this->categoria ?: null,
            'ativo' => $this->ativo,
        ];

        if ($this->editandoId) {
            CatalogoItem::findOrFail($this->editandoId)->update($dados);
        } else {
            CatalogoItem::create($dados);
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Item de catálogo salvo com sucesso.');
    }

    public function excluir(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        if (SaldoEstoque::where('item_catalogo_id', $id)->exists()) {
            $this->addError('excluir', 'Não é possível excluir: existem saldos de estoque vinculados a este item. Desvincule-os primeiro na tela de Reconciliação de Saldos.');

            return;
        }

        CatalogoItem::findOrFail($id)->delete();
        $this->dispatch('notify', mensagem: 'Item de catálogo removido.');
    }

    // ─── Modal mínimos por unidade ────────────────────────────────────────────

    public function abrirModalMinimos(int $itemId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $item = CatalogoItem::findOrFail($itemId);
        $this->minimoItemId = $item->id;
        $this->minimoItemDescricao = $item->descricao;

        // Carrega todas as unidades ativas com o mínimo atual (se existir)
        $minimosExistentes = EstoqueMinimo::where('item_catalogo_id', $item->id)
            ->pluck('quantidade_minima', 'unidade_id');

        $this->minimosPorUnidade = Unidade::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get()
            ->map(fn (Unidade $u) => [
                'unidade_id' => $u->id,
                'nome' => $u->nome,
                'quantidade_minima' => isset($minimosExistentes[$u->id])
                    ? (string) (float) $minimosExistentes[$u->id]
                    : '',
            ])
            ->toArray();

        $this->mostrarModalMinimos = true;
    }

    public function fecharModalMinimos(): void
    {
        $this->mostrarModalMinimos = false;
        $this->minimoItemId = null;
        $this->minimoItemDescricao = '';
        $this->minimosPorUnidade = [];
        $this->resetValidation();
    }

    public function salvarMinimoUnidade(int $unidadeId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        if (! $this->minimoItemId) {
            return;
        }

        $indice = collect($this->minimosPorUnidade)->search(fn ($m) => (int) $m['unidade_id'] === $unidadeId);

        if ($indice === false) {
            return;
        }

        $quantidade = (float) ($this->minimosPorUnidade[$indice]['quantidade_minima'] ?? '0');

        $unidade = Unidade::withoutGlobalScopes()->findOrFail($unidadeId);
        $item = CatalogoItem::findOrFail($this->minimoItemId);

        try {
            app(DefinirEstoqueMinimoAction::class)->execute(
                $unidade,
                $item,
                $quantidade,
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError("minimo_{$unidadeId}", $e->getMessage());

            return;
        }

        $mensagem = $quantidade <= 0
            ? 'Estoque mínimo removido.'
            : 'Estoque mínimo salvo com sucesso.';

        $this->dispatch('notify', mensagem: $mensagem);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $itens = CatalogoItem::query()
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('descricao', 'like', "%{$this->busca}%")
                    ->orWhere('codigo', 'like', "%{$this->busca}%");
            }))
            ->when($this->filtroAtivo !== '', fn ($q) => $q->where('ativo', (bool) $this->filtroAtivo))
            ->orderBy('descricao')
            ->paginate(15);

        return view('livewire.admin.catalogo-itens.lista-catalogo-itens', compact('itens'))
            ->layout('components.layouts.app');
    }
}
