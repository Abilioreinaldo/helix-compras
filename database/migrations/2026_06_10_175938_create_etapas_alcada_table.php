<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de etapas de aprovação dentro de cada faixa de alçada.
     */
    public function up(): void
    {
        Schema::create('etapas_alcada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faixa_alcada_id')->constrained('faixas_alcada')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->string('nivel_exigido');
            $table->timestamps();

            $table->unique(['faixa_alcada_id', 'ordem']);
        });
    }

    /**
     * Remove a tabela de etapas de alçada.
     */
    public function down(): void
    {
        Schema::dropIfExists('etapas_alcada');
    }
};
