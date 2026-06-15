<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('itens_pedido_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained('pedidos_compra')->cascadeOnDelete();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->restrictOnDelete();
            $table->foreignId('item_requisicao_id')->constrained('requisicao_itens')->restrictOnDelete();
            $table->foreignId('cotacao_id')->constrained('cotacoes')->restrictOnDelete();
            // Snapshot congelado na emissão
            $table->string('descricao');
            $table->decimal('quantidade', 15, 3);
            $table->string('unidade_medida', 20)->nullable();
            $table->decimal('valor_unitario', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->string('destino', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pedido_compra_id', 'item_requisicao_id']);
            $table->index('pedido_compra_id');
            $table->index('requisicao_id');
            $table->index('cotacao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itens_pedido_compra');
    }
};
