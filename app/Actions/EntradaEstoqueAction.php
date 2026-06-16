<?php

namespace App\Actions;

use App\Enums\TipoMovimentacao;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRecebimento;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Database\QueryException;
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
        // Tombstones de fusão (fundido_para_id != null) nunca entram — a entrada credita
        // sempre no saldo ativo (destino) ou cria um novo.
        // lockForUpdate garante atomicidade em MySQL/MariaDB; em SQLite é defensivo.
        $base = SaldoEstoque::where('unidade_id', $unidadeId)
            ->where('deposito', $deposito)
            ->whereNull('fundido_para_id');

        if ($catalogoId !== null) {
            $base->where('item_catalogo_id', $catalogoId);
        } else {
            $base->where('descricao_normalizada', $descricaoNormalizada)
                ->whereNull('item_catalogo_id');
        }

        $saldo = (clone $base)->lockForUpdate()->first();

        if ($saldo === null) {
            try {
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
            } catch (QueryException $e) {
                // Corrida: outra transação criou o saldo entre o SELECT e o INSERT.
                // Degrada para o caminho de atualização re-buscando o saldo recém-criado,
                // em vez de estourar erro 500. Outras violações de integridade propagam.
                if (! $this->ehViolacaoUnicidadeCatalogo($e)) {
                    throw $e;
                }

                $saldo = (clone $base)->lockForUpdate()->first();

                if ($saldo === null) {
                    throw $e;
                }
            }
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

    /**
     * Indica se a exceção é uma violação do UNIQUE de identidade de catálogo
     * (saldos_estoque_catalogo_unique), e não outra constraint que deve propagar.
     */
    private function ehViolacaoUnicidadeCatalogo(QueryException $e): bool
    {
        $codigo = $e->errorInfo[1] ?? null;
        $mensagem = $e->getMessage();

        // MySQL/MariaDB: ER_DUP_ENTRY (1062) cita o nome do índice na mensagem.
        if ($codigo === 1062) {
            return str_contains($mensagem, 'saldos_estoque_catalogo_unique');
        }

        // SQLite: SQLITE_CONSTRAINT (19). A mensagem de UNIQUE lista as colunas do índice
        // (não o nome); item_catalogo_id distingue do UNIQUE legado de descricao_normalizada.
        if ($codigo === 19) {
            return str_contains($mensagem, 'UNIQUE constraint failed')
                && str_contains($mensagem, 'item_catalogo_id');
        }

        return false;
    }
}
