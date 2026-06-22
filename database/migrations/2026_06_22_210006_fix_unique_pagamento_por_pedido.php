<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O unique composto (pedido_compra_id, deleted_at) NÃO previne duplicatas ativas:
        // em SQLite e MySQL, NULL é distinto em índice único, então (X, NULL) duplica.
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropUnique(['pedido_compra_id', 'deleted_at']);
        });

        // Índice ÚNICO PARCIAL "1 pagamento ATIVO por pedido" — driver-aware.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX pagamentos_pedido_ativo_unique ON pagamentos (pedido_compra_id) WHERE deleted_at IS NULL');
        } else {
            DB::statement('ALTER TABLE pagamentos ADD COLUMN pedido_ativo_key BIGINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN pedido_compra_id ELSE NULL END) STORED');
            DB::statement('CREATE UNIQUE INDEX pagamentos_pedido_ativo_unique ON pagamentos (pedido_ativo_key)');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('DROP INDEX pagamentos_pedido_ativo_unique ON pagamentos');
            DB::statement('ALTER TABLE pagamentos DROP COLUMN pedido_ativo_key');
        } else {
            DB::statement('DROP INDEX IF EXISTS pagamentos_pedido_ativo_unique');
        }

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->unique(['pedido_compra_id', 'deleted_at']);
        });
    }
};
