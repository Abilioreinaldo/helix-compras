<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de itens de uma requisição de compra.
     */
    public function up(): void
    {
        Schema::create('requisicao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->cascadeOnDelete();
            $table->string('descricao', 255);
            $table->decimal('quantidade', 15, 3);
            $table->string('unidade_medida', 10)->nullable();
            $table->decimal('valor_unitario_estimado', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove a tabela de itens de requisição.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicao_itens');
    }
};
