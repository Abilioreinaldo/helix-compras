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
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_estoque_id')->constrained('saldos_estoque')->restrictOnDelete();
            // Nullable: preenchido apenas em movimentações originadas de recebimento
            $table->foreignId('item_recebimento_id')->nullable()->constrained('itens_recebimento')->restrictOnDelete();
            // Gancho F8: via este FK → itens_pedido_compra → pedidos_compra.{prazo_entrega, modalidade_entrega}
            $table->foreignId('item_pedido_compra_id')->nullable()->constrained('itens_pedido_compra')->restrictOnDelete();
            $table->enum('tipo', ['entrada', 'saida', 'ajuste_positivo', 'ajuste_negativo']);
            $table->decimal('quantidade', 15, 3);
            $table->decimal('custo_unitario', 15, 4);
            $table->decimal('valor_total', 15, 2);
            $table->text('motivo')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            // Sem softDeletes — append-only; correções são novas linhas

            $table->index('saldo_estoque_id');
            $table->index('tipo');
            $table->index('item_pedido_compra_id');
            $table->index('registrado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
