<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de obras (extensão 1-1 de unidades do tipo obra).
     */
    public function up(): void
    {
        Schema::create('obras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->unique()->constrained('unidades')->cascadeOnDelete();
            $table->date('iniciada_em');
            $table->date('previsao_termino')->nullable();
            $table->date('encerrada_em')->nullable();
            $table->string('status')->default('ativa');
            $table->decimal('verba', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove a tabela de obras.
     */
    public function down(): void
    {
        Schema::dropIfExists('obras');
    }
};
