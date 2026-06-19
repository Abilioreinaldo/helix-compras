<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag de auditoria (v1.1-C): marca a movimentação de SAÍDA que consumiu um lote
     * com validade < hoje (vencido). Default false; entradas/ajustes ficam false.
     * Índice composto (saldo_estoque_id, lote_vencido_em_saida) para auditoria futura
     * ("saídas com lote vencido por saldo") sem varrer LIKE no campo motivo.
     *
     * Sem ->after(): SQLite (testes) não posiciona coluna em ALTER TABLE; a posição é
     * irrelevante e o MySQL aceita coluna no fim — portável sem ramo de driver.
     */
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->boolean('lote_vencido_em_saida')->default(false);
            $table->index(['saldo_estoque_id', 'lote_vencido_em_saida'], 'mov_estoque_saldo_vencido_idx');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropIndex('mov_estoque_saldo_vencido_idx');
            $table->dropColumn('lote_vencido_em_saida');
        });
    }
};
