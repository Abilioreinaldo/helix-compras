<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * UNIQUE de identidade de catálogo em saldos_estoque (Passo 3 / v1.1-B).
     *
     * Garante no banco que não existam dois saldos ATIVOS com a mesma
     * (unidade_id, deposito, item_catalogo_id). Ficam de fora — por isso o índice é
     * PARCIAL — os saldos avulsos (item_catalogo_id NULL) e os tombstones de fusão
     * (fundido_para_id != NULL). Sem o filtro `fundido_para_id IS NULL`, o tombstone e o
     * saldo destino colidiriam e a constraint bloquearia um saldo ativo legítimo.
     * O UNIQUE legado (unidade_id, deposito, descricao_normalizada) é PRESERVADO — esta
     * migration não o toca.
     *
     * Pré-requisito de produção: rodar `estoque:sanear-duplicatas-catalogo` (Passo 2)
     * ANTES desta migration. Duplicatas legadas não saneadas fazem a criação do índice
     * falhar — comportamento correto: força o saneamento antes do constraint.
     *
     * Driver-aware:
     * - SQLite: índice UNIQUE parcial nativo (CREATE UNIQUE INDEX ... WHERE ...).
     * - MySQL/MariaDB: NÃO suporta índice parcial; usa coluna gerada STORED que fica NULL
     *   quando a linha está fora do escopo (avulso ou tombstone), com UNIQUE sobre ela —
     *   NULLs não colidem, reproduzindo a semântica do índice parcial do SQLite.
     *   Separador CHAR(31) (unit separator ASCII) entre os campos — impossível em nome de
     *   depósito, evita colisão de chave; VARCHAR(600) acomoda deposito de até 255 chars.
     *
     * ATENÇÃO (ponto cego de teste): a suíte roda só em SQLite. O caminho MySQL desta
     * migration — que é produção — NÃO é exercitado por nenhum teste automatizado. Precisa
     * ser validado num MySQL real (migrate + insert de duplicata) antes do go-live.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                'ALTER TABLE saldos_estoque ADD COLUMN catalogo_chave_unica VARCHAR(600) '
                .'GENERATED ALWAYS AS (CASE WHEN item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL '
                .'THEN CONCAT(unidade_id, CHAR(31), deposito, CHAR(31), item_catalogo_id) ELSE NULL END) STORED'
            );
            DB::statement('CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque (catalogo_chave_unica)');

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque '
            .'(unidade_id, deposito, item_catalogo_id) '
            .'WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL'
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('DROP INDEX saldos_estoque_catalogo_unique ON saldos_estoque');
            DB::statement('ALTER TABLE saldos_estoque DROP COLUMN catalogo_chave_unica');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS saldos_estoque_catalogo_unique');
    }
};
