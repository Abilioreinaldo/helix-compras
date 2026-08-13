<?php

namespace App\Actions;

use App\Enums\StatusRequisicao;
use App\Models\PedidoLojaRecebido;
use App\Models\Requisicao;
use App\Models\User;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Promove um pedido da loja (inbox ADR-015) a Requisicao do Compras.
 *
 * O comprador escolhe unidade e centro de custo — o pedido da loja não traz
 * essas entidades (mundos diferentes). A Requisicao nasce em RASCUNHO com o
 * comprador como solicitante e os itens do snapshot como avulsos (descrição,
 * quantidade e custo estimado vêm do contrato); ele revisa/submete pelo fluxo
 * normal — a promoção NÃO pula triagem nem alçada.
 */
class PromoverPedidoLojaAction
{
    /**
     * @throws ValidationException
     */
    public function execute(PedidoLojaRecebido $pedido, User $comprador, int $unidadeId, int $centroCustoId): Requisicao
    {
        return DB::transaction(function () use ($pedido, $comprador, $unidadeId, $centroCustoId) {
            $pedido = PedidoLojaRecebido::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->status !== PedidoLojaRecebido::STATUS_RECEBIDO) {
                throw ValidationException::withMessages([
                    'pedido' => 'Este pedido da loja já foi promovido ou descartado.',
                ]);
            }

            $requisicao = new Requisicao;
            $requisicao->solicitante_id = $comprador->id;
            $requisicao->fill([
                'unidade_id' => $unidadeId,
                'centro_custo_id' => $centroCustoId,
                'status' => StatusRequisicao::Rascunho,
            ]);
            $requisicao->save();

            foreach ($pedido->payload['lines'] ?? [] as $line) {
                $requisicao->itens()->create([
                    'descricao' => trim(($line['product_code'] ?? '').' — '.($line['product_name'] ?? 'Item da loja'), ' —'),
                    'quantidade' => ($line['qty_thousandths'] ?? 1000) / 1000,
                    'valor_unitario_estimado' => isset($line['last_unit_cost_cents'])
                        ? $line['last_unit_cost_cents'] / 100
                        : null,
                    'avulso' => true,
                ]);
            }

            $pedido->update([
                'status' => PedidoLojaRecebido::STATUS_PROMOVIDO,
                'requisicao_id' => $requisicao->id,
            ]);

            // ESCOPO (D10, ponte dual): evento de domínio + audit_log da foundation.
            app(ActivityRecorder::class)->record('compras.pedido_loja_promovido', $requisicao, $requisicao->tenant_id, [
                'actor_id' => $comprador->id,
                'metadata' => [
                    'request_code' => $pedido->request_code,
                    'store_code' => $pedido->store_code,
                    'pedido_loja_id' => $pedido->id,
                ],
            ]);

            return $requisicao;
        });
    }
}
