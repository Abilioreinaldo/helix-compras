<?php

namespace App\Actions;

use App\Enums\TipoMovimentacao;
use App\Models\CatalogoItem;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRecebimento;
use App\Models\LoteEstoque;
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
     * Lote/validade (v1.1-C): quando o item de catálogo vinculado tem
     * `controla_lote = true`, `numeroLote` é obrigatório e a entrada credita um
     * `LoteEstoque` pendurado no saldo — somando à quantidade do lote vivo de mesmo
     * número (2º recebimento) ou criando um novo — mantendo a invariante
     * SUM(lotes vivos) == saldo.quantidade. `validade` é opcional (NULL = sem validade).
     *
     * A flag `controla_lote` é lida do catálogo via `itemPedidoCompra->item_catalogo_id`
     * (única fonte: `ItemRecebimento` não carrega catálogo). Itens sem `controla_lote`
     * ignoram `numeroLote`/`validade` e mantêm comportamento idêntico ao anterior.
     *
     * @throws ValidationException
     */
    public function execute(
        ItemPedidoCompra $itemPedidoCompra,
        ItemRecebimento $itemRecebimento,
        float $quantidade,
        User $registradoPor,
        ?string $numeroLote = null,
        ?string $validade = null,
    ): MovimentacaoEstoque {
        // O item do pedido é a fonte canônica da entrada (destino, custo, catálogo);
        // deriva-se do recebimento para não depender de o chamador passar o par coerente.
        $itemPedidoCompra = $itemRecebimento->itemPedidoCompra;

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

        $catalogoId = $itemPedidoCompra->item_catalogo_id;

        // Flag de controle de lote: vive no CatalogoItem, alcançada SÓ pelo item do pedido
        // (ItemRecebimento não tem item_catalogo_id). Item avulso (sem catálogo) nunca controla.
        $controlaLote = $catalogoId !== null
            && (bool) CatalogoItem::withTrashed()->whereKey($catalogoId)->value('controla_lote');

        $numeroLote = $numeroLote !== null ? trim($numeroLote) : null;

        // Guard ANTES de qualquer escrita: item que controla lote exige número de lote.
        // Falhar aqui impede criar saldo órfão (saldo creditado sem o lote correspondente).
        if ($controlaLote && ($numeroLote === null || $numeroLote === '')) {
            throw ValidationException::withMessages([
                'numero_lote' => "Item \"{$itemPedidoCompra->descricao}\" controla lote — informe o número do lote para registrar a entrada.",
            ]);
        }

        $pedido = $itemPedidoCompra->pedidoCompra()->withoutGlobalScopes()->first();
        $unidadeId = $pedido->unidade_id;
        $deposito = $itemPedidoCompra->destino;
        $descricaoItem = $itemPedidoCompra->descricao;
        $descricaoNormalizada = SaldoEstoque::normalizarDescricao($descricaoItem);
        $custoUnitario = (float) $itemPedidoCompra->valor_unitario;

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

        // Lote: credita/soma na mesma transação para manter SUM(lotes vivos) == saldo.quantidade.
        // Apenas itens controla_lote chegam aqui com lote; demais mantêm lote_estoque_id null.
        $loteId = null;

        if ($controlaLote) {
            $loteId = $this->creditarLote($saldo, $numeroLote, $validade, $quantidade)->id;
        }

        return MovimentacaoEstoque::create([
            'saldo_estoque_id' => $saldo->id,
            'item_recebimento_id' => $itemRecebimento->id,
            'item_pedido_compra_id' => $itemPedidoCompra->id,
            'lote_estoque_id' => $loteId,
            'tipo' => TipoMovimentacao::Entrada,
            'quantidade' => $quantidade,
            'custo_unitario' => $custoUnitario,
            'valor_total' => $quantidade * $custoUnitario,
            'motivo' => null,
            'registrado_por' => $registradoPor->id,
        ]);
    }

    /**
     * Credita a quantidade no lote vivo de mesmo número (somando) ou cria um novo.
     *
     * Mantém a invariante SUM(lotes vivos) == saldo.quantidade: a mesma quantidade
     * creditada no saldo é creditada em exatamente um lote. A validade do lote
     * existente é preservada no 2º recebimento (a primeira entrada define a validade).
     */
    private function creditarLote(SaldoEstoque $saldo, string $numeroLote, ?string $validade, float $quantidade): LoteEstoque
    {
        $lote = LoteEstoque::where('saldo_estoque_id', $saldo->id)
            ->where('numero_lote', $numeroLote)
            ->whereNull('fundido_para_id')
            ->lockForUpdate()
            ->first();

        if ($lote === null) {
            try {
                return LoteEstoque::create([
                    'saldo_estoque_id' => $saldo->id,
                    'numero_lote' => $numeroLote,
                    'validade' => ($validade !== null && $validade !== '') ? $validade : null,
                    'quantidade' => $quantidade,
                ]);
            } catch (QueryException $e) {
                // Corrida: outra transação inseriu o mesmo lote vivo entre o SELECT e o INSERT.
                // Degrada para soma, re-buscando o lote recém-criado. Outras violações propagam.
                if (! $this->ehViolacaoUnicidadeLote($e)) {
                    throw $e;
                }

                $lote = LoteEstoque::where('saldo_estoque_id', $saldo->id)
                    ->where('numero_lote', $numeroLote)
                    ->whereNull('fundido_para_id')
                    ->lockForUpdate()
                    ->first();

                if ($lote === null) {
                    throw $e;
                }
            }
        }

        // 2º recebimento do mesmo lote vivo: soma à quantidade existente (não duplica).
        $lote->update(['quantidade' => (float) $lote->quantidade + $quantidade]);

        return $lote;
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

    /**
     * Indica se a exceção é uma violação do UNIQUE parcial de lote vivo
     * (lotes_estoque_saldo_lote_unique), e não outra constraint que deve propagar.
     */
    private function ehViolacaoUnicidadeLote(QueryException $e): bool
    {
        $codigo = $e->errorInfo[1] ?? null;
        $mensagem = $e->getMessage();

        // MySQL/MariaDB: ER_DUP_ENTRY (1062) cita o nome do índice na mensagem.
        if ($codigo === 1062) {
            return str_contains($mensagem, 'lotes_estoque_saldo_lote_unique');
        }

        // SQLite: SQLITE_CONSTRAINT (19). A mensagem de UNIQUE lista as colunas do índice
        // parcial; exigir numero_lote distingue de outras constraints futuras da tabela
        // (a FK reporta "FOREIGN KEY constraint failed", que não casa com o filtro acima).
        if ($codigo === 19) {
            return str_contains($mensagem, 'UNIQUE constraint failed')
                && str_contains($mensagem, 'numero_lote');
        }

        return false;
    }
}
