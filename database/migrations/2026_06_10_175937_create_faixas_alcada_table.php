<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de faixas de alçada para aprovação de compras.
     */
    public function up(): void
    {
        Schema::create('faixas_alcada', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->decimal('valor_minimo', 15, 2)->default(0);
            $table->decimal('valor_maximo', 15, 2)->nullable();
            $table->boolean('is_emergencial')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Remove a tabela de faixas de alçada.
     */
    public function down(): void
    {
        Schema::dropIfExists('faixas_alcada');
    }
};
