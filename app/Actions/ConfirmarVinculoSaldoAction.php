<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ConfirmarVinculoSaldoAction
{
    /**
     * Vincula um saldo de estoque a um item de catálogo.
     *
     * Idempotente: se já vinculado ao mesmo item, é no-op. Se vinculado a outro item,
     * exige desvinculação prévia. Bloqueia colisão com saldo já existente na mesma
     * identidade (unidade, depósito, catálogo) — fusão de saldos é fora de escopo.
     * Nunca altera quantidade/custo_medio_ponderado/valor_total.
     *
     * @throws ValidationException
     */
    public function vincular(SaldoEstoque $saldo, CatalogoItem $item, User $admin): SaldoEstoque
    {
        abort_unless($admin->temPerfil(Perfil::Admin), 403);

        if ($saldo->item_catalogo_id === $item->id) {
            return $saldo;
        }

        if ($saldo->item_catalogo_id !== null) {
            throw ValidationException::withMessages([
                'item_catalogo_id' => 'Este saldo já está vinculado a outro item de catálogo. Desvincule antes de vincular a um novo item.',
            ]);
        }

        // Exclui tombstones de fusão (fundido_para_id != null): eles estão fora do índice
        // parcial e não representam um saldo ativo — não devem bloquear a vinculação.
        $colisao = SaldoEstoque::where('unidade_id', $saldo->unidade_id)
            ->where('deposito', $saldo->deposito)
            ->where('item_catalogo_id', $item->id)
            ->where('id', '!=', $saldo->id)
            ->whereNull('fundido_para_id')
            ->exists();

        if ($colisao) {
            throw ValidationException::withMessages([
                'item_catalogo_id' => 'Já existe um saldo vinculado a este item de catálogo nesta unidade/depósito. A fusão de saldos não é suportada.',
            ]);
        }

        try {
            $saldo->update(['item_catalogo_id' => $item->id]);
        } catch (QueryException $e) {
            // Corrida: o pré-check passou, mas outro Admin vinculou um saldo à mesma
            // identidade antes deste UPDATE. O UNIQUE do banco barra; convertemos para
            // a mesma mensagem de colisão em vez de estourar erro 500.
            if (! $this->ehViolacaoUnicidadeCatalogo($e)) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'item_catalogo_id' => 'Já existe um saldo vinculado a este item de catálogo nesta unidade/depósito. A fusão de saldos não é suportada.',
            ]);
        }

        return $saldo;
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
     * Remove o vínculo de catálogo de um saldo, retornando-o ao estado avulso.
     *
     * @throws ValidationException
     */
    public function desvincular(SaldoEstoque $saldo, User $admin): SaldoEstoque
    {
        abort_unless($admin->temPerfil(Perfil::Admin), 403);

        if ($saldo->item_catalogo_id === null) {
            return $saldo;
        }

        $saldo->update(['item_catalogo_id' => null]);

        return $saldo;
    }
}
