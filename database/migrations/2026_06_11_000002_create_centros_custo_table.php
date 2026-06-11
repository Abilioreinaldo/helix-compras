<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de centros de custo (pertence a uma unidade).
     */
    public function up(): void
    {
        Schema::create('centros_custo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nome', 150);
            $table->foreignId('gestor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['unidade_id', 'codigo', 'deleted_at']);
            $table->index(['unidade_id', 'ativo']);
            $table->index('gestor_id');
        });
    }

    /**
     * Remove a tabela de centros de custo.
     */
    public function down(): void
    {
        Schema::dropIfExists('centros_custo');
    }
};
