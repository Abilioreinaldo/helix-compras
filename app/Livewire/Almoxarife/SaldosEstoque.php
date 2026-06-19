<?php

namespace App\Livewire\Almoxarife;

use App\Actions\DefinirEstoqueMinimoAction;
use App\Actions\TransferirEstoqueAction;
use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    // ─── Modal transferência entre unidades ───────────────────────────────────

    public ?int $transferindoSaldoId = null;

    public string $transferDescricaoItem = '';

    public string $transferDestinoId = '';

    public string $transferQuantidade = '';

    public string $transferMotivo = '';

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

    public function abrirTransferencia(int $saldoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $saldo = SaldoEstoque::whereIn('unidade_id', $this->unidadesDoAlmoxarife())->findOrFail($saldoId);

        $this->transferindoSaldoId = $saldo->id;
        $this->transferDescricaoItem = $saldo->descricao_item;
        $this->transferDestinoId = '';
        $this->transferQuantidade = '';
        $this->transferMotivo = '';
        $this->resetValidation();
    }

    public function cancelarTransferencia(): void
    {
        $this->transferindoSaldoId = null;
        $this->transferDescricaoItem = '';
        $this->transferDestinoId = '';
        $this->transferQuantidade = '';
        $this->transferMotivo = '';
        $this->resetValidation();
    }

    public function confirmarTransferencia(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $this->validate([
            'transferDestinoId' => ['required', Rule::exists('unidades', 'id')->whereNull('deleted_at')],
            'transferQuantidade' => 'required|numeric|min:0.001',
            'transferMotivo' => 'nullable|string|max:1000',
        ], [
            'transferDestinoId.required' => 'Selecione a unidade de destino.',
            'transferQuantidade.required' => 'Informe a quantidade a transferir.',
        ]);

        // O saldo precisa pertencer a uma unidade onde o usuário é Almoxarife.
        $saldo = SaldoEstoque::whereIn('unidade_id', $this->unidadesDoAlmoxarife())->findOrFail($this->transferindoSaldoId);
        $destino = Unidade::withoutGlobalScopes()->findOrFail((int) $this->transferDestinoId);

        try {
            app(TransferirEstoqueAction::class)->execute(
                $saldo,
                $destino,
                (float) $this->transferQuantidade,
                $this->transferMotivo ?: '',
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('transferQuantidade', collect($e->errors())->flatten()->first() ?? 'Falha na transferência.');

            return;
        }

        $this->cancelarTransferencia();
        $this->dispatch('notify', mensagem: 'Transferência realizada com sucesso.');
    }

    /** @return Collection<int, int> */
    private function unidadesDoAlmoxarife()
    {
        return auth()->user()->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->pluck('unidades.id');
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

        $validades = LoteEstoque::validadesVivasPorSaldo($saldos->pluck('id'));

        // Unidades de destino para transferência (rede inteira; a action bloqueia a própria origem).
        $unidadesDestino = Unidade::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('livewire.almoxarife.saldos-estoque', compact('saldos', 'depositos', 'idsEmAlerta', 'itensARepor', 'validades', 'unidadesDestino'))
            ->layout('components.layouts.app');
    }
}
