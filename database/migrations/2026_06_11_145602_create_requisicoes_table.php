<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de requisições de compra.
     */
    public function up(): void
    {
        Schema::create('requisicoes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique()->nullable();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->foreignId('centro_custo_id')->constrained('centros_custo')->restrictOnDelete();
            $table->foreignId('obra_id')->nullable()->constrained('obras')->restrictOnDelete();
            $table->string('status')->default('rascunho');
            $table->boolean('urgente')->default(false);
            $table->boolean('is_emergencial')->default(false);
            $table->text('justificativa')->nullable();
            $table->boolean('atrasada')->default(false);
            $table->foreignId('faixa_alcada_id')->nullable()->constrained('faixas_alcada')->restrictOnDelete();
            $table->boolean('escalada_verba')->default(false);
            $table->decimal('consumo_verba_no_submit', 15, 2)->nullable();
            $table->timestamp('submetida_em')->nullable();
            $table->timestamp('triagem_iniciada_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unidade_id', 'status']);
            $table->index(['solicitante_id', 'status']);
            $table->index(['obra_id', 'status']);
            $table->index(['status', 'submetida_em']);
        });
    }

    /**
     * Remove a tabela de requisições de compra.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicoes');
    }
};
