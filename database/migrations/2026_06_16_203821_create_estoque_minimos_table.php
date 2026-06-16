<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de estoques mínimos por (unidade × item de catálogo).
     */
    public function up(): void
    {
        Schema::create('estoque_minimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->foreignId('item_catalogo_id')->constrained('catalogo_itens')->restrictOnDelete();
            $table->decimal('quantidade_minima', 15, 3);
            $table->timestamps();

            $table->unique(['unidade_id', 'item_catalogo_id'], 'estoque_minimos_identidade_unique');
            $table->index('item_catalogo_id');
        });
    }

    /**
     * Remove a tabela de estoques mínimos.
     */
    public function down(): void
    {
        Schema::dropIfExists('estoque_minimos');
    }
};
