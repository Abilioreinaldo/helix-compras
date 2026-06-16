<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->foreignId('requisicao_material_id')
                ->nullable()
                ->after('item_pedido_compra_id')
                ->constrained('requisicoes_material')
                ->restrictOnDelete();

            $table->index('requisicao_material_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropForeign(['requisicao_material_id']);
            $table->dropIndex(['requisicao_material_id']);
            $table->dropColumn('requisicao_material_id');
        });
    }
};
