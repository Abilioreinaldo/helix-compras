<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona vínculo opcional ao catálogo de itens para reconciliação de saldos (v1.1-A).
     * Não altera o UNIQUE existente (unidade_id, deposito, descricao_normalizada) — a
     * unicidade real por catálogo é garantida na lógica de ConfirmarVinculoSaldoAction.
     */
    public function up(): void
    {
        Schema::table('saldos_estoque', function (Blueprint $table) {
            $table->foreignId('item_catalogo_id')->nullable()->after('descricao_normalizada')
                ->constrained('catalogo_itens')->restrictOnDelete();

            $table->index(['unidade_id', 'deposito', 'item_catalogo_id'], 'saldos_estoque_catalogo_idx');
        });
    }

    /**
     * Remove o vínculo ao catálogo de itens.
     */
    public function down(): void
    {
        Schema::table('saldos_estoque', function (Blueprint $table) {
            $table->dropIndex('saldos_estoque_catalogo_idx');
            $table->dropConstrainedForeignId('item_catalogo_id');
        });
    }
};
