<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->restrictOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
            $table->decimal('valor', 15, 2);
            $table->unsignedSmallInteger('prazo_entrega_dias')->nullable();
            $table->string('arquivo_path', 500)->nullable();
            $table->string('arquivo_nome_original', 255)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('vencedora')->default(false);
            $table->foreignId('criada_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('vencedora_definida_em')->nullable();
            $table->foreignId('vencedora_definida_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('requisicao_id');
            $table->index(['requisicao_id', 'vencedora']);
            $table->index('fornecedor_id');
            $table->unique(['requisicao_id', 'fornecedor_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacoes');
    }
};
