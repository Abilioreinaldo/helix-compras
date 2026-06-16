<?php

namespace App\Livewire\Almoxarife;

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

class SaldosEstoque extends Component
{
    use WithPagination;

    public string $busca = '';

    public string $deposito = '';

    // ─── Modal definir mínimo ─────────────────────────────────────────────────

    public bool $mostrarModalMinimo = false;

    public ?int $minimoSaldoId = null;

    public ?int $minimoItemCatalogoId = null;

    public string $minimoDescricaoItem = '';

    public string $minimoUnidadeId = '';

    public string $minimoQuantidade = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);
    }

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    public function updatingDeposito(): void
    {
        $this->resetPage();
    }

    public function abrirModalMinimo(int $saldoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        // Restringe o saldo às unidades onde o usuário é Almoxarife — não vazar dados de outra unidade.
        $unidadeIds = auth()->user()->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');

        $saldo = SaldoEstoque::whereIn('unidade_id', $unidadeIds)->findOrFail($saldoId);

        if (! $saldo->item_catalogo_id) {
            return;
        }

        $this->minimoSaldoId = $saldoId;
        $this->minimoItemCatalogoId = $saldo->item_catalogo_id;
        $this->minimoDescricaoItem = $saldo->descricao_item;
        $this->minimoUnidadeId = (string) $saldo->unidade_id;

        $existente = EstoqueMinimo::where('unidade_id', $saldo->unidade_id)
            ->where('item_catalogo_id', $saldo->item_catalogo_id)
            ->value('quantidade_minima');

        $this->minimoQuantidade = $existente !== null ? (string) (float) $existente : '';
        $this->mostrarModalMinimo = true;
    }

    public function fecharModalMinimo(): void
    {
        $this->mostrarModalMinimo = false;
        $this->minimoSaldoId = null;
        $this->minimoItemCatalogoId = null;
        $this->minimoDescricaoItem = '';
        $this->minimoUnidadeId = '';
        $this->minimoQuantidade = '';
        $this->resetValidation();
    }

    public function salvarMinimo(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $this->validate([
            'minimoQuantidade' => 'required|numeric|min:0',
            'minimoUnidadeId' => ['required', Rule::exists('unidades', 'id')->whereNull('deleted_at')],
            'minimoItemCatalogoId' => ['required', Rule::exists('catalogo_itens', 'id')->whereNull('deleted_at')],
        ], [
            'minimoQuantidade.required' => 'Informe a quantidade mínima (0 para remover).',
            'minimoQuantidade.numeric' => 'A quantidade deve ser um número.',
            'minimoQuantidade.min' => 'A quantidade não pode ser negativa.',
        ]);

        // withoutGlobalScopes: o guard de autorização na action valida o vínculo do usuário
        $unidade = Unidade::withoutGlobalScopes()->findOrFail((int) $this->minimoUnidadeId);
        $item = CatalogoItem::withoutGlobalScopes()->findOrFail($this->minimoItemCatalogoId);

        try {
            app(DefinirEstoqueMinimoAction::class)->execute(
                $unidade,
                $item,
                (float) $this->minimoQuantidade,
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('minimoQuantidade', $e->getMessage());

            return;
        }

        $this->fecharModalMinimo();
        $this->dispatch('notify', mensagem: 'Estoque mínimo salvo com sucesso.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $usuario = auth()->user();

        $unidadeIds = $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');

        $query = SaldoEstoque::with('unidade')
            ->whereIn('unidade_id', $unidadeIds);

        if ($this->busca !== '') {
            $query->where('descricao_normalizada', 'like', '%'.SaldoEstoque::normalizarDescricao($this->busca).'%');
        }

        if ($this->deposito !== '') {
            $query->where('deposito', $this->deposito);
        }

        $saldos = $query->orderBy('deposito')->orderBy('descricao_item')->paginate(30);

        $depositos = SaldoEstoque::whereIn('unidade_id', $unidadeIds)
            ->distinct()
            ->orderBy('deposito')
            ->pluck('deposito');

        $idsEmAlerta = EstoqueMinimo::itemCatalogoIdsEmAlerta($unidadeIds->toArray());

        $itensARepor = EstoqueMinimo::itensAReporPara($usuario);

        return view('livewire.almoxarife.saldos-estoque', compact('saldos', 'depositos', 'idsEmAlerta', 'itensARepor'))
            ->layout('components.layouts.app');
    }
}
