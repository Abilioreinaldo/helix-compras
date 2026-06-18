<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaidaEstoqueAction
{
    /**
     * Registra uma saída de estoque pelo CMP vigente (não altera o CMP).
     *
     * Item sem `controla_lote`: ramo legado (uma movimentação no saldo agregado).
     * Item com `controla_lote`: ramo FEFO (First Expiry First Out) — debita os lotes vivos
     * por ordem de validade, uma movimentação por lote consumido, `custo_unitario = CMP do
     * saldo` (NÃO é PEPS valorizado). Lote vencido é consumido assim mesmo, com alerta
     * anotado no ledger (nunca bloqueia a saída). A invariante SUM(lotes vivos) == saldo
     * é reverificada após o lock e reafirmada antes do commit.
     *
     * @param  bool  $atendimentoDireto  Contexto: saída disparada pelo atendimento direto de
     *                                   requisição (TriagemRequisicoes). É a ÚNICA via pela qual
     *                                   a CompradoraSenior fica autorizada a baixar saldo — fora
     *                                   desse fluxo ela não pode dar saída avulsa.
     *
     * @throws ValidationException
     */
    public function execute(
        SaldoEstoque $saldo,
        float $quantidade,
        string $motivo,
        User $registradoPor,
        bool $atendimentoDireto = false,
    ): MovimentacaoEstoque {
        if ($quantidade <= 0) {
            throw ValidationException::withMessages([
                'quantidade' => 'A quantidade de saída deve ser maior que zero.',
            ]);
        }

        // Autorização de saída:
        // - Almoxarife da unidade do saldo: saída normal (ex.: atendimento de RIM).
        // - Admin: irrestrito.
        // - CompradoraSenior: SOMENTE no contexto de atendimento direto ($atendimentoDireto=true).
        //   Sem esse contexto ela NÃO baixa saldo avulso.
        $almoxarifeDaUnidade = $registradoPor->unidades()
            ->withoutGlobalScopes()
            ->where('unidades.id', $saldo->unidade_id)
            ->wherePivot('perfil', Perfil::Almoxarife->value)
            ->exists();

        $autorizado = $almoxarifeDaUnidade
            || $registradoPor->temPerfil(Perfil::Admin)
            || ($atendimentoDireto && $registradoPor->temPerfil(Perfil::CompradoraSenior));

        if (! $autorizado) {
            throw ValidationException::withMessages([
                'saldo' => 'Operação não permitida: usuário sem autorização para saída neste saldo.',
            ]);
        }

        return DB::transaction(function () use ($saldo, $quantidade, $motivo, $registradoPor) {
            // withoutGlobalScopes: relocking by id — unidade já foi verificada acima
            $saldo = SaldoEstoque::withoutGlobalScopes()->where('id', $saldo->id)->lockForUpdate()->firstOrFail();

            $qtdDisponivel = (float) $saldo->quantidade;

            if ($quantidade > $qtdDisponivel + 0.001) {
                throw ValidationException::withMessages([
                    'quantidade' => 'Saldo insuficiente. Disponível: '.number_format($qtdDisponivel, 3, ',', '.').'.',
                ]);
            }

            // Guard logo após o relock: item sem controle de lote cai no ramo legado
            // (movido byte-a-byte do v1); com controle de lote entra no FEFO.
            if (! $this->itemControlaLote($saldo)) {
                $cmpVigente = (float) $saldo->custo_medio_ponderado;
                // Clamp to 0: a tolerância de 0.001 permite passar quantidades marginalmente
                // acima do saldo para evitar falsos positivos de ponto flutuante.
                $qtdNova = max(0.0, $qtdDisponivel - $quantidade);

                $saldo->update([
                    'quantidade' => $qtdNova,
                    // CMP não se altera na saída
                    'valor_total' => $qtdNova * $cmpVigente,
                ]);

                return MovimentacaoEstoque::create([
                    'saldo_estoque_id' => $saldo->id,
                    'item_recebimento_id' => null,
                    'item_pedido_compra_id' => null,
                    'tipo' => TipoMovimentacao::Saida,
                    'quantidade' => $quantidade,
                    'custo_unitario' => $cmpVigente,
                    'valor_total' => $quantidade * $cmpVigente,
                    'motivo' => $motivo,
                    'registrado_por' => $registradoPor->id,
                ]);
            }

            return $this->baixarFefo($saldo, $quantidade, $motivo, $registradoPor, $qtdDisponivel);
        });
    }

    /**
     * O item de catálogo do saldo controla lote? Flag vive no CatalogoItem.
     * withTrashed: catálogo soft-deleted não pode rebaixar o saldo para o ramo legado
     * (deixaria lotes vivos sem baixa, quebrando a invariante SUM(lotes)==saldo).
     */
    private function itemControlaLote(SaldoEstoque $saldo): bool
    {
        return $saldo->item_catalogo_id !== null
            && (bool) CatalogoItem::withTrashed()->whereKey($saldo->item_catalogo_id)->value('controla_lote');
    }

    /**
     * Baixa FEFO: debita os lotes vivos por ordem de validade até cobrir a quantidade.
     *
     * - Lock de cada lote (lockForUpdate) na mesma transação do saldo.
     * - Reverificação pós-lock: SUM(lotes vivos) == saldo.quantidade ANTES de debitar.
     * - Uma MovimentacaoEstoque por lote consumido, custo_unitario = CMP do saldo (não PEPS),
     *   lote_estoque_id setado; o ledger é append-only (N movimentações por saída multi-lote).
     * - Lote vencido (validade < hoje) é consumido com alerta anotado no motivo, nunca lança.
     * - Assert da invariante reafirmado antes do commit; transação única reverte tudo na falha.
     *
     * @throws ValidationException
     */
    private function baixarFefo(
        SaldoEstoque $saldo,
        float $quantidade,
        string $motivo,
        User $registradoPor,
        float $qtdDisponivel,
    ): MovimentacaoEstoque {
        $cmpVigente = (float) $saldo->custo_medio_ponderado;

        // Lotes vivos em ordem FEFO, travados para consumo. Mesma ordenação portável do
        // SelecaoFefoService (validade IS NULL, validade ASC, id ASC), agora com lockForUpdate.
        // Filtro estrito por saldo_estoque_id via lotesVivos() — nunca item_catalogo_id solto.
        $lotes = $saldo->lotesVivos()
            ->orderByRaw('validade IS NULL')
            ->orderBy('validade')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Reverificação pós-lock: a invariante tem de valer ANTES de debitar; se não vale,
        // há corrupção de dados — aborta e reverte a transação em vez de piorar o estado.
        $somaAntes = (float) $lotes->sum(fn (LoteEstoque $lote) => (float) $lote->quantidade);
        if (abs($somaAntes - $qtdDisponivel) > 0.001) {
            throw new \RuntimeException(
                "Invariante de lote violada no saldo {$saldo->id}: SUM(lotes)={$somaAntes} != saldo={$qtdDisponivel}."
            );
        }

        $hoje = now()->startOfDay();
        $restante = $quantidade;
        $ultimaMovimentacao = null;

        foreach ($lotes as $lote) {
            if ($restante <= 1e-9) {
                break;
            }

            $loteQtd = (float) $lote->quantidade;
            if ($loteQtd <= 0.0) {
                continue;
            }

            $consumir = min($loteQtd, $restante);
            $lote->update(['quantidade' => max(0.0, $loteQtd - $consumir)]);
            $restante = max(0.0, $restante - $consumir);

            // Vencido: validade estritamente anterior a hoje. Consome assim mesmo; anota o
            // alerta no ledger (motivo) para a UI sinalizar. Nunca lança.
            $vencido = $lote->validade !== null && $lote->validade->lt($hoje);
            $motivoMovimentacao = $vencido
                ? $motivo." [ALERTA: lote {$lote->numero_lote} vencido em {$lote->validade->format('Y-m-d')}]"
                : $motivo;

            $ultimaMovimentacao = MovimentacaoEstoque::create([
                'saldo_estoque_id' => $saldo->id,
                'item_recebimento_id' => null,
                'item_pedido_compra_id' => null,
                'lote_estoque_id' => $lote->id,
                'tipo' => TipoMovimentacao::Saida,
                'quantidade' => $consumir,
                'custo_unitario' => $cmpVigente, // CMP do saldo, não PEPS valorizado
                'valor_total' => $consumir * $cmpVigente,
                'motivo' => $motivoMovimentacao,
                'registrado_por' => $registradoPor->id,
            ]);
        }

        // Defensivo: com a invariante válida e saldo suficiente, os lotes cobrem a saída.
        // Se sobrou quantidade a debitar, algo está inconsistente — aborta (reverte tudo).
        if ($restante > 0.001 || $ultimaMovimentacao === null) {
            throw new \RuntimeException(
                "Lotes não cobriram a saída no saldo {$saldo->id}: faltam {$restante}."
            );
        }

        $qtdNova = max(0.0, $qtdDisponivel - $quantidade);
        $saldo->update([
            'quantidade' => $qtdNova,
            // CMP não se altera na saída
            'valor_total' => $qtdNova * $cmpVigente,
        ]);

        // Assert da invariante pós-baixa antes do commit: SUM(lotes vivos) == saldo.quantidade.
        $somaDepois = (float) $saldo->lotesVivos()->sum('quantidade');
        if (abs($somaDepois - $qtdNova) > 0.001) {
            throw new \RuntimeException(
                "Invariante de lote violada pós-baixa no saldo {$saldo->id}: SUM(lotes)={$somaDepois} != saldo={$qtdNova}."
            );
        }

        return $ultimaMovimentacao;
    }
}
