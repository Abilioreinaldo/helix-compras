<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained('pedidos_compra')->restrictOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
            $table->foreignId('banco_id')->nullable()->constrained('bancos')->nullOnDelete();

            $table->string('numero_nf')->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_vencimento');

            $table->decimal('valor_total', 15, 2);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_multa', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);

            $table->string('status')->default('pendente');
            $table->string('metodo_pagamento')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->string('referencia_banco')->nullable();
            $table->string('numero_cheque')->nullable();
            $table->date('agendado_para')->nullable();
            $table->text('observacoes')->nullable();

            $table->foreignId('criado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Um pagamento por pedido (idempotência da geração automática).
            $table->unique(['pedido_compra_id', 'deleted_at']);
            $table->index('status');
            $table->index('data_vencimento');
            $table->index('fornecedor_id');
            $table->index('referencia_banco');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
