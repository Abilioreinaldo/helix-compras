<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adiciona 'rateio_central' e 'desconto_rateio' ao tipo de movimentacoes_estoque.
     *
     * Driver-aware:
     * - SQLite: a coluna `tipo` já é TEXT puro (sem CHECK) desde add_fusao — aceita
     *   os novos valores sem qualquer ALTER. Nada a fazer.
     * - MySQL/MariaDB: amplia o ENUM. (Ponto cego: validar em MySQL real — checklist.)
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE movimentacoes_estoque MODIFY COLUMN tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo','fusao','rateio_central','desconto_rateio') NOT NULL");
        }
    }

    /**
     * Reverte ao ENUM sem os tipos de rateio (assume zero linhas desses tipos no MySQL).
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE movimentacoes_estoque MODIFY COLUMN tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo','fusao') NOT NULL");
        }
    }
};
