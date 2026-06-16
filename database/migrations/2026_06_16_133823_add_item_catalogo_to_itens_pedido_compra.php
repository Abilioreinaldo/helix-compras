<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona vínculo opcional ao catálogo de itens e marcação de item avulso.
     */
    public function up(): void
    {
        Schema::table('itens_pedido_compra', function (Blueprint $table) {
            $table->foreignId('item_catalogo_id')->nullable()->after('descricao')
                ->constrained('catalogo_itens')->restrictOnDelete();
            $table->boolean('avulso')->default(true)->after('item_catalogo_id');

            $table->index('item_catalogo_id');
        });
    }

    /**
     * Remove o vínculo ao catálogo de itens.
     */
    public function down(): void
    {
        Schema::table('itens_pedido_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_catalogo_id');
            $table->dropColumn('avulso');
        });
    }
};
