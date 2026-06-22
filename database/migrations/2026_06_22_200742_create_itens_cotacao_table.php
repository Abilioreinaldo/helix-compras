<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_cotacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotacao_id')->constrained('cotacoes')->cascadeOnDelete();
            $table->foreignId('item_requisicao_id')->constrained('requisicao_itens')->cascadeOnDelete();
            // Preço UNITÁRIO cotado pelo fornecedor para o item. A linha vale
            // valor_unitario × quantidade do item; o total da cotação é a soma das linhas.
            $table->decimal('valor_unitario', 15, 2);
            $table->timestamps();

            // Um preço por item por cotação.
            $table->unique(['cotacao_id', 'item_requisicao_id']);
            $table->index('item_requisicao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_cotacao');
    }
};
