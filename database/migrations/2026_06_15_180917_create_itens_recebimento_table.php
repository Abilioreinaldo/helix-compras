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
        Schema::create('itens_recebimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recebimento_id')->constrained('recebimentos')->cascadeOnDelete();
            $table->foreignId('item_pedido_compra_id')->constrained('itens_pedido_compra')->restrictOnDelete();
            $table->decimal('quantidade_recebida', 15, 3);
            $table->timestamps();
            $table->softDeletes();

            $table->index('recebimento_id');
            $table->index('item_pedido_compra_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itens_recebimento');
    }
};
