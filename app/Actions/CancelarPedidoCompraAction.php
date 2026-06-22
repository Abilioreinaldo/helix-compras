<?php

namespace App\Actions;

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\ItemPedidoCompra;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelarPedidoCompraAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(PedidoCompra $pedido, User $usuario, string $motivo): void
    {
        DB::transaction(function () use ($pedido, $usuario, $motivo) {
            $pedido->refresh();

            if ($pedido->status === StatusPedidoCompra::Cancelado) {
                throw ValidationException::withMessages([
                    'status' => 'Este pedido já está cancelado.',
                ]);
            }

            if ($pedido->status === StatusPedidoCompra::Emitido && empty(trim($motivo))) {
                throw ValidationException::withMessages([
                    'motivo' => 'Informe o motivo do cancelamento.',
                ]);
            }

            $eraEmitido = $pedido->status === StatusPedidoCompra::Emitido;

            $pedido->update([
                'status' => StatusPedidoCompra::Cancelado,
                'cancelado_em' => now(),
                'cancelado_por' => $usuario->id,
                'motivo_cancelamento' => $motivo ?: null,
            ]);

            if ($eraEmitido) {
                // Reverter requisições para Aprovada, mas só se não houver outro PC emitido cobrindo-as
                // reorder(): a relação itens() ordena por requisicao_id+id; com DISTINCT em só
                // requisicao_id, o ORDER BY id quebra no MySQL (ONLY_FULL_GROUP_BY). A ordem é
                // irrelevante aqui — só iteramos os ids.
                $requisicaoIds = $pedido->itens()->reorder()->distinct()->pluck('requisicao_id');

                foreach ($requisicaoIds as $requisicaoId) {
                    $temOutroPC = ItemPedidoCompra::query()
                        ->join('pedidos_compra', 'itens_pedido_compra.pedido_compra_id', '=', 'pedidos_compra.id')
                        ->where('itens_pedido_compra.requisicao_id', $requisicaoId)
                        ->where('pedidos_compra.status', StatusPedidoCompra::Emitido->value)
                        ->where('pedidos_compra.id', '!=', $pedido->id)
                        ->whereNull('itens_pedido_compra.deleted_at')
                        ->whereNull('pedidos_compra.deleted_at')
                        ->exists();

                    if (! $temOutroPC) {
                        $requisicao = Requisicao::withoutGlobalScopes()->find($requisicaoId);
                        if ($requisicao && $requisicao->status === StatusRequisicao::EmCompra) {
                            $this->transicionar->execute(
                                $requisicao,
                                StatusRequisicao::Aprovada,
                                "Pedido de compra {$pedido->numero} cancelado: {$motivo}",
                                true
                            );
                        }
                    }
                }
            }
        });
    }
}
