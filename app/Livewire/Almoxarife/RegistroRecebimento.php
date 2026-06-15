<?php

namespace App\Livewire\Almoxarife;

use App\Actions\RegistrarRecebimentoAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
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

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Almoxarife), 403);

        $pedido = $this->carregarPedido();
        abort_unless($pedido->status === StatusPedidoCompra::Emitido, 403);
        $this->autorizarAcesso($pedido);

        $this->id = $id;

        foreach ($pedido->itens as $item) {
            $this->quantidades[$item->id] = '';
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

        try {
            app(RegistrarRecebimentoAction::class)->execute(
                $pedido,
                auth()->user(),
                $qtds,
                $this->observacoes ?: null
            );
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('recebimento', $mensagem);

            return;
        }

        session()->flash('sucesso', 'Recebimento registrado com sucesso.');
        $this->redirect(route('almoxarife.recebimentos.index'));
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

        // Quantidade já recebida por item
        $jaRecebidoPorItem = DB::table('itens_recebimento')
            ->join('recebimentos', 'itens_recebimento.recebimento_id', '=', 'recebimentos.id')
            ->where('recebimentos.pedido_compra_id', $pedido->id)
            ->whereNull('itens_recebimento.deleted_at')
            ->whereNull('recebimentos.deleted_at')
            ->groupBy('itens_recebimento.item_pedido_compra_id')
            ->pluck(DB::raw('SUM(itens_recebimento.quantidade_recebida)'), 'itens_recebimento.item_pedido_compra_id');

        return view('livewire.almoxarife.registro-recebimento', compact('pedido', 'jaRecebidoPorItem'))
            ->layout('components.layouts.app');
    }
}
