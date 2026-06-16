<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona colunas de fusão em saldos_estoque (v1.1-B).
     * Ambas nullable — saldos não fundidos mantêm NULL.
     * fundido_para_id aponta para o saldo destino (self-FK com restrictOnDelete).
     */
    public function up(): void
    {
        Schema::table('saldos_estoque', function (Blueprint $table) {
            // Self-FK: saldo tombstone aponta para o saldo destino
            $table->foreignId('fundido_para_id')
                ->nullable()
                ->after('item_catalogo_id')
                ->constrained('saldos_estoque')
                ->restrictOnDelete();

            $table->timestamp('fundido_em')->nullable()->after('fundido_para_id');

            $table->index('fundido_para_id', 'saldos_estoque_fundido_para_idx');
        });
    }

    /**
     * Remove colunas de fusão de saldos_estoque.
     */
    public function down(): void
    {
        Schema::table('saldos_estoque', function (Blueprint $table) {
            $table->dropIndex('saldos_estoque_fundido_para_idx');
            $table->dropConstrainedForeignId('fundido_para_id');
            $table->dropColumn('fundido_em');
        });
    }
};
