<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de lotes de estoque (Passo 0 / v1.1-C).
     *
     * Cada linha representa um lote vinculado a um SaldoEstoque agregado.
     * Unidade e depósito são herdados via saldo_estoque_id — não duplicados aqui.
     *
     * UNIQUE parcial (saldo_estoque_id, numero_lote) em fundido_para_id IS NULL:
     * o mesmo número de lote pode reaparecer após tombstone (2º recebimento somado ao lote
     * existente é tratado no Passo 2). Lotes fundidos (tombstones) não colidem.
     *
     * Driver-aware (mesma técnica da migration add_unique_catalogo_to_saldos_estoque):
     * - SQLite: índice UNIQUE parcial nativo (CREATE UNIQUE INDEX ... WHERE ...).
     * - MySQL/MariaDB: coluna gerada STORED que fica NULL fora do escopo (tombstones),
     *   com UNIQUE sobre ela — NULLs não colidem no MySQL.
     *
     * ATENÇÃO: o caminho MySQL NÃO é exercitado pela suíte (SQLite). Validar num MySQL real
     * antes do go-live (checklist D9).
     */
    public function up(): void
    {
        Schema::create('lotes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_estoque_id')->constrained('saldos_estoque')->restrictOnDelete();
            $table->string('numero_lote');
            $table->date('validade')->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            // Tombstone: quando fundido, aponta para o lote destino
            $table->foreignId('fundido_para_id')->nullable()->constrained('lotes_estoque')->restrictOnDelete();
            $table->dateTime('fundido_em')->nullable();
            $table->timestamps();

            $table->index('saldo_estoque_id');
            $table->index(['saldo_estoque_id', 'validade'], 'lotes_estoque_fefo_idx');
            $table->index('fundido_para_id');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL não suporta índice parcial; usa coluna gerada STORED que fica NULL
            // quando a linha é tombstone (fundido_para_id != NULL), reproduzindo a semântica.
            // Separador CHAR(31) (unit separator ASCII) entre os campos — impossível em
            // numero_lote legítimo, evita colisão de chave.
            DB::statement(
                'ALTER TABLE lotes_estoque ADD COLUMN lote_chave_unica VARCHAR(600) '
                .'GENERATED ALWAYS AS (CASE WHEN fundido_para_id IS NULL '
                .'THEN CONCAT(saldo_estoque_id, CHAR(31), numero_lote) ELSE NULL END) STORED'
            );
            DB::statement('CREATE UNIQUE INDEX lotes_estoque_saldo_lote_unique ON lotes_estoque (lote_chave_unica)');

            return;
        }

        // SQLite: índice UNIQUE parcial nativo
        DB::statement(
            'CREATE UNIQUE INDEX lotes_estoque_saldo_lote_unique ON lotes_estoque '
            .'(saldo_estoque_id, numero_lote) '
            .'WHERE fundido_para_id IS NULL'
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('DROP INDEX lotes_estoque_saldo_lote_unique ON lotes_estoque');
            DB::statement('ALTER TABLE lotes_estoque DROP COLUMN lote_chave_unica');
        } else {
            DB::statement('DROP INDEX IF EXISTS lotes_estoque_saldo_lote_unique');
        }

        Schema::dropIfExists('lotes_estoque');
    }
};
