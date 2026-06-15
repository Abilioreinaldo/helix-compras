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
        Schema::create('pedidos_compra', function (Blueprint $table) {
            $table->id();
            // Número gerado apenas na emissão; rascunhos têm null
            $table->string('numero', 14)->nullable()->unique();
            $table->unsignedSmallInteger('ano')->nullable();
            $table->unsignedInteger('sequencia')->nullable();
            $table->enum('status', ['rascunho', 'emitido', 'cancelado'])->default('rascunho');
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->text('condicoes_pagamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('emitido_em')->nullable();
            $table->foreignId('emitido_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelado_em')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ano', 'sequencia']);
            $table->index(['status', 'unidade_id']);
            $table->index(['status', 'emitido_em']);
            $table->index('fornecedor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_compra');
    }
};
