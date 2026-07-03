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
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('pagamentos', function (Blueprint $table) {
                $table->dropUnique(['pedido_compra_id', 'deleted_at']);
            });

            // Índice ÚNICO PARCIAL "1 pagamento ATIVO por pedido".
            DB::statement('CREATE UNIQUE INDEX pagamentos_pedido_ativo_unique ON pagamentos (pedido_compra_id) WHERE deleted_at IS NULL');
        } else {
            // MySQL: o unique composto é o índice que dá suporte à FK de pedido_compra_id.
            // Dropá-lo direto falha com erro 1553 ("needed in a foreign key constraint").
            // Cria-se um índice simples na coluna ANTES do drop para a FK continuar coberta
            // (o unique novo fica sobre a coluna gerada pedido_ativo_key, que NÃO cobre a FK).
            Schema::table('pagamentos', function (Blueprint $table) {
                $table->index('pedido_compra_id', 'pagamentos_pedido_compra_id_index');
                $table->dropUnique(['pedido_compra_id', 'deleted_at']);
            });

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

            // Re-cria o unique composto (volta a cobrir a FK) e só então remove o índice simples.
            Schema::table('pagamentos', function (Blueprint $table) {
                $table->unique(['pedido_compra_id', 'deleted_at']);
                $table->dropIndex('pagamentos_pedido_compra_id_index');
            });
        } else {
            DB::statement('DROP INDEX IF EXISTS pagamentos_pedido_ativo_unique');

            Schema::table('pagamentos', function (Blueprint $table) {
                $table->unique(['pedido_compra_id', 'deleted_at']);
            });
        }
    }
};
