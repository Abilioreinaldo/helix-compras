<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona lote_estoque_id em movimentacoes_estoque (Passo 0 / v1.1-C).
     *
     * Nullable — movimentações existentes (sem lote) permanecem inalteradas.
     * Obrigatório no #[Fillable] do model; sem ele o valor é descartado silenciosamente.
     */
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->foreignId('lote_estoque_id')
                ->nullable()
                ->constrained('lotes_estoque')
                ->restrictOnDelete()
                ->after('requisicao_material_id');

            $table->index('lote_estoque_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropForeign(['lote_estoque_id']);
            $table->dropIndex(['lote_estoque_id']);
            $table->dropColumn('lote_estoque_id');
        });
    }
};
