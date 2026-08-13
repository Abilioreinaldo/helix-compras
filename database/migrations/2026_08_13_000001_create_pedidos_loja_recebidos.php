<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbox de pedidos de compra vindos da loja (Store), via transporte inter-app
 * assinado (ADR-015). NÃO é uma Requisicao: é uma zona de pouso idempotente
 * (tenant_id + request_code) com o snapshot do contrato. O comprador promove
 * a Requisicao depois (mapeando loja→unidade, CNPJ→fornecedor, itens).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_loja_recebidos', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->string('request_code');
            $table->string('store_code')->nullable();
            $table->string('supplier_cnpj', 14)->nullable();
            $table->string('supplier_name')->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->unsignedBigInteger('total_estimated_cents')->default(0);
            $table->json('payload');
            // recebido → promovido (virou Requisicao) | descartado (rejeitado)
            $table->string('status')->default('recebido');
            $table->foreignId('requisicao_id')->nullable()->constrained('requisicoes')->nullOnDelete();
            $table->timestamp('recebido_em')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            // Idempotência do consumidor (ADR-015): entrega at-least-once, uma
            // linha por pedido da loja por tenant.
            $table->unique(['tenant_id', 'request_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_loja_recebidos');
    }
};
