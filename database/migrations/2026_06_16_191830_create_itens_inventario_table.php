<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessao_inventario_id')->constrained('sessoes_inventario')->cascadeOnDelete();
            $table->foreignId('saldo_estoque_id')->constrained('saldos_estoque')->restrictOnDelete();
            $table->decimal('quantidade_sistema', 15, 3);
            $table->decimal('quantidade_contada', 15, 3)->nullable();
            $table->foreignId('movimentacao_estoque_id')->nullable()->constrained('movimentacoes_estoque')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['sessao_inventario_id', 'saldo_estoque_id'], 'itens_inventario_sessao_saldo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_inventario');
    }
};
