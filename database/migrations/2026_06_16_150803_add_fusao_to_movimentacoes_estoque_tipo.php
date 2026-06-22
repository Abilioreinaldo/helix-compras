<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona o valor 'fusao' ao campo tipo de movimentacoes_estoque.
     *
     * Driver-aware:
     * - SQLite: nao suporta ALTER COLUMN. Recriamos a coluna como TEXT puro.
     *   O indice movimentacoes_estoque_tipo_index depende da coluna tipo,
     *   entao precisa ser dropado ANTES do drop da coluna e recriado DEPOIS do rename.
     * - MySQL/MariaDB: ALTER TABLE amplia o ENUM sem perder dados.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE movimentacoes_estoque MODIFY COLUMN tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo','fusao') NOT NULL");

            return;
        }

        // SQLite: substitui a coluna (com check constraint) por TEXT puro.
        // O indice sobre 'tipo' impede o DROP COLUMN, entao dropa antes e recria depois.
        Schema::table('movimentacoes_estoque', function ($table) {
            $table->dropIndex('movimentacoes_estoque_tipo_index');
        });

        DB::statement("ALTER TABLE movimentacoes_estoque ADD COLUMN tipo_novo TEXT NOT NULL DEFAULT 'entrada'");
        DB::statement('UPDATE movimentacoes_estoque SET tipo_novo = tipo');
        DB::statement('ALTER TABLE movimentacoes_estoque DROP COLUMN tipo');
        DB::statement('ALTER TABLE movimentacoes_estoque RENAME COLUMN tipo_novo TO tipo');

        Schema::table('movimentacoes_estoque', function ($table) {
            $table->index('tipo', 'movimentacoes_estoque_tipo_index');
        });
    }

    /**
     * Reverte: restaura a coluna com os 4 valores originais.
     * ATENCAO: registros com tipo='fusao' violariam o check restaurado —
     * o down() assume que nao existem linhas 'fusao' (ambiente de teste/dev).
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE movimentacoes_estoque MODIFY COLUMN tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo') NOT NULL");

            return;
        }

        // SQLite: mesmo cuidado com o indice na volta.
        Schema::table('movimentacoes_estoque', function ($table) {
            $table->dropIndex('movimentacoes_estoque_tipo_index');
        });

        DB::statement("ALTER TABLE movimentacoes_estoque ADD COLUMN tipo_orig TEXT NOT NULL DEFAULT 'entrada' CHECK (tipo_orig IN ('entrada','saida','ajuste_positivo','ajuste_negativo'))");
        DB::statement("UPDATE movimentacoes_estoque SET tipo_orig = tipo WHERE tipo IN ('entrada','saida','ajuste_positivo','ajuste_negativo')");
        DB::statement('ALTER TABLE movimentacoes_estoque DROP COLUMN tipo');
        DB::statement('ALTER TABLE movimentacoes_estoque RENAME COLUMN tipo_orig TO tipo');

        Schema::table('movimentacoes_estoque', function ($table) {
            $table->index('tipo', 'movimentacoes_estoque_tipo_index');
        });
    }
};
