<?php

namespace App\Actions;

use App\Enums\TipoMovimentacao;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRecebimento;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EntradaEstoqueAction
{
    /**
     * Registra uma entrada de mercadoria no estoque e recalcula o CMP.
     *
     * Deve ser chamada DENTRO de uma DB::transaction já aberta pelo chamador
     * (RegistrarRecebimentoAction), pois a atomicidade é de responsabilidade dele.
     *
     * SQLite serializa escritas por transação (modo DEFERRED) — lockForUpdate é
     * defensivo aqui; em MySQL/MariaDB de produção passa a ser necessário para
     * evitar race conditions reais entre transações concorrentes.
     *
     * @throws ValidationException
     */
    public function execute(
        ItemPedidoCompra $itemPedidoCompra,
        ItemRecebimento $itemRecebimento,
        float $quantidade,
        User $registradoPor,
    ): MovimentacaoEstoque {
        if (empty($itemPedidoCompra->destino)) {
            throw ValidationException::withMessages([
                'destino' => "Item \"{$itemPedidoCompra->descricao}\" não tem destino definido — não é possível dar entrada no estoque.",
            ]);
        }

        if ($quantidade <= 0) {
            throw ValidationException::withMessages([
                'quantidade' => 'A quantidade de entrada deve ser maior que zero.',
            ]);
        }

        $pedido = $itemPedidoCompra->pedidoCompra()->withoutGlobalScopes()->first();
        $unidadeId = $pedido->unidade_id;
        $deposito = $itemPedidoCompra->destino;
        $descricaoItem = $itemPedidoCompra->descricao;
        $descricaoNormalizada = SaldoEstoque::normalizarDescricao($descricaoItem);
        $custoUnitario = (float) $itemPedidoCompra->valor_unitario;
        $catalogoId = $itemPedidoCompra->item_catalogo_id;

        // Identidade do saldo: catálogo quando vinculado, descrição normalizada quando avulso.
        // lockForUpdate garante atomicidade em MySQL/MariaDB; em SQLite é defensivo
        $query = SaldoEstoque::where('unidade_id', $unidadeId)
            ->where('deposito', $deposito);

        if ($catalogoId !== null) {
            $query->where('item_catalogo_id', $catalogoId);
        } else {
            $query->where('descricao_normalizada', $descricaoNormalizada)
                ->whereNull('item_catalogo_id');
        }

        $saldo = $query->lockForUpdate()->first();

        if ($saldo === null) {
            $saldo = SaldoEstoque::create([
                'unidade_id' => $unidadeId,
                'deposito' => $deposito,
                'descricao_item' => $descricaoItem,
                'descricao_normalizada' => $descricaoNormalizada,
                'unidade_medida' => $itemPedidoCompra->unidade_medida,
                'quantidade' => 0,
                'custo_medio_ponderado' => 0,
                'valor_total' => 0,
                'item_catalogo_id' => $catalogoId,
            ]);
        }

        $qtdAtual = (float) $saldo->quantidade;
        $valorAtual = (float) $saldo->valor_total;

        // Custo médio ponderado: (valor_atual + qtd_nova × custo_nova) / (qtd_atual + qtd_nova)
        $qtdNova = $qtdAtual + $quantidade;
        $valorNovo = $valorAtual + ($quantidade * $custoUnitario);
        $novoCmp = $qtdNova > 0 ? $valorNovo / $qtdNova : 0;

        $saldo->update([
            'quantidade' => $qtdNova,
            'custo_medio_ponderado' => $novoCmp,
            'valor_total' => $qtdNova * $novoCmp,
        ]);

        return MovimentacaoEstoque::create([
            'saldo_estoque_id' => $saldo->id,
            'item_recebimento_id' => $itemRecebimento->id,
            'item_pedido_compra_id' => $itemPedidoCompra->id,
            'tipo' => TipoMovimentacao::Entrada,
            'quantidade' => $quantidade,
            'custo_unitario' => $custoUnitario,
            'valor_total' => $quantidade * $custoUnitario,
            'motivo' => null,
            'registrado_por' => $registradoPor->id,
        ]);
    }
}
