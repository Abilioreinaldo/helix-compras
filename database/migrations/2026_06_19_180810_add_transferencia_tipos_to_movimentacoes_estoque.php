<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adiciona 'transferencia_saida' e 'transferencia_entrada' ao tipo de movimentacoes_estoque.
     *
     * Driver-aware: SQLite já tem `tipo` como TEXT puro (desde add_fusao) — no-op. MySQL amplia
     * o ENUM. (Ponto cego: validar em MySQL real — checklist.)
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE movimentacoes_estoque MODIFY COLUMN tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo','fusao','rateio_central','desconto_rateio','transferencia_saida','transferencia_entrada') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE movimentacoes_estoque MODIFY COLUMN tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo','fusao','rateio_central','desconto_rateio') NOT NULL");
        }
    }
};
