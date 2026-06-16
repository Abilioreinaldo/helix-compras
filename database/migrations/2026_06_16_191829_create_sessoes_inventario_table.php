<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessoes_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->string('deposito', 255)->nullable();
            $table->foreignId('aberta_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('concluida_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['em_andamento', 'concluido', 'cancelado'])->default('em_andamento');
            $table->text('justificativa')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->index('unidade_id');
            $table->index('status');
            $table->index(['unidade_id', 'deposito', 'status'], 'sessoes_inventario_unidade_deposito_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessoes_inventario');
    }
};
