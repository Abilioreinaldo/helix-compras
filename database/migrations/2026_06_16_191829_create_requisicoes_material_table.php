<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisicoes_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('almoxarife_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('saldo_estoque_id')->constrained('saldos_estoque')->restrictOnDelete();
            $table->decimal('quantidade_solicitada', 15, 3);
            $table->text('justificativa');
            $table->enum('status', ['aberta', 'atendida', 'recusada'])->default('aberta');
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('movimentacao_estoque_id')->nullable()->constrained('movimentacoes_estoque')->restrictOnDelete();
            $table->timestamp('atendida_em')->nullable();
            $table->timestamp('recusada_em')->nullable();
            $table->timestamps();

            $table->index('unidade_id');
            $table->index('status');
            $table->index('saldo_estoque_id');
            $table->index('solicitante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicoes_material');
    }
};
