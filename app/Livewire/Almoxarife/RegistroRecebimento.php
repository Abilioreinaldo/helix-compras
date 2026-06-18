<?php

namespace App\Livewire\Almoxarife;

use App\Actions\RegistrarRecebimentoAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Models\CatalogoItem;
use App\Models\PedidoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RegistroRecebimento extends Component
{
    public int $id;

    public string $observacoes = '';

    /** @var array<int, string> item_pedido_compra_id => quantidade_str */
    public array $quantidades = [];

    /** @var array<int, array{numero_lote: string, validade: string}> item_pedido_compra_id => dados do lote (só itens controla_lote) */
    public array $lotes = [];

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status === StatusPedidoCompra::Emitido, 403);
        $this->autorizarAcesso($pedido);

        $this->id = $id;

        $controlaLote = $this->controlaLotePorItem($pedido);

        foreach ($pedido->itens as $item) {
            $this->quantidades[$item->id] = '';

            if ($controlaLote[$item->id] ?? false) {
                $this->lotes[$item->id] = ['numero_lote' => '', 'validade' => ''];
            }
        }
    }

    public function registrar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status === StatusPedidoCompra::Emitido, 403);
        $this->autorizarAcesso($pedido);

        $this->validate([
            'observacoes' => 'nullable|string|max:2000',
            'quantidades' => 'array',
            'quantidades.*' => 'nullable|numeric|min:0',
        ]);

        $qtds = collect($this->quantidades)
            ->map(fn ($v) => $v === '' ? 0.0 : (float) $v)
            ->filter(fn ($v) => $v > 0)
            ->toArray();

        $controlaLote = $this->controlaLotePorItem($pedido);

        // Itens controla_lote sendo recebidos exigem número de lote (validação inline; o
        // EntradaEstoqueAction também barra no backend como rede de segurança).
        $lotes = [];
        foreach ($qtds as $itemId => $qtd) {
            if (! ($controlaLote[$itemId] ?? false)) {
                continue;
            }

            $numero = trim((string) ($this->lotes[$itemId]['numero_lote'] ?? ''));

            if ($numero === '') {
                $this->addError("lotes.{$itemId}.numero_lote", 'Informe o número do lote para este item.');

                return;
            }

            $validade = trim((string) ($this->lotes[$itemId]['validade'] ?? ''));

            $lotes[$itemId] = [
                'numero_lote' => $numero,
                'validade' => $validade !== '' ? $validade : null,
            ];
        }

        try {
            app(RegistrarRecebimentoAction::class)->execute(
                $pedido,
                auth()->user(),
                $qtds,
                $this->observacoes ?: null,
                $lotes,
            );
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('recebimento', $mensagem);

            return;
        }

        session()->flash('sucesso', 'Recebimento registrado com sucesso.');
        $this->redirect(route('almoxarife.recebimentos.index'));
    }

    /**
     * Mapa item_pedido_compra_id => bool (o item controla lote). withTrashed para casar com
     * o enforcement do EntradaEstoqueAction (catálogo arquivado mas controla_lote ainda conta).
     *
     * @return array<int, bool>
     */
    private function controlaLotePorItem(PedidoCompra $pedido): array
    {
        $catalogoIds = $pedido->itens->pluck('item_catalogo_id')->filter()->unique();

        $controlam = $catalogoIds->isEmpty()
            ? collect()
            : CatalogoItem::withTrashed()
                ->whereIn('id', $catalogoIds)
                ->where('controla_lote', true)
                ->pluck('id')
                ->flip();

        $mapa = [];
        foreach ($pedido->itens as $item) {
            $mapa[$item->id] = $item->item_catalogo_id !== null && $controlam->has($item->item_catalogo_id);
        }

        return $mapa;
    }

    private function carregarPedido(): PedidoCompra
    {
        return PedidoCompra::withoutGlobalScopes()
            ->with(['itens', 'fornecedor', 'unidade', 'recebimentos.itens'])
            ->findOrFail($this->id);
    }

    private function autorizarAcesso(PedidoCompra $pedido): void
    {
        $temAcesso = (bool) DB::table('unidade_user')
            ->where('user_id', auth()->id())
            ->where('unidade_id', $pedido->unidade_id)
            ->where('perfil', Perfil::Almoxarife->value)
            ->exists();

        abort_unless($temAcesso, 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $pedido = $this->carregarPedido();
        $this->autorizarAcesso($pedido);

        $controlaLote = $this->controlaLotePorItem($pedido);

        // Quantidade já recebida por item
        $jaRecebidoPorItem = DB::table('itens_recebimento')
            ->join('recebimentos', 'itens_recebimento.recebimento_id', '=', 'recebimentos.id')
            ->where('recebimentos.pedido_compra_id', $pedido->id)
            ->whereNull('itens_recebimento.deleted_at')
            ->whereNull('recebimentos.deleted_at')
            ->groupBy('itens_recebimento.item_pedido_compra_id')
            ->pluck(DB::raw('SUM(itens_recebimento.quantidade_recebida)'), 'itens_recebimento.item_pedido_compra_id');

        return view('livewire.almoxarife.registro-recebimento', compact('pedido', 'jaRecebidoPorItem', 'controlaLote'))
            ->layout('components.layouts.app');
    }
}
