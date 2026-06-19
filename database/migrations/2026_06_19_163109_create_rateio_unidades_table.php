<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Linha por unidade dentro de um rateio: percentual de consumo (gasto da unidade /
     * gasto total da rede no mês) e o valor rateado correspondente. UNIQUE (rateio, unidade).
     * percentual_consumo: decimal(7,4) — ex.: 0.3333 = 33,33%.
     */
    public function up(): void
    {
        Schema::create('rateio_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rateio_central_id')->constrained('rateios_centrais')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->decimal('percentual_consumo', 7, 4);
            $table->decimal('valor_rateado', 15, 2);
            $table->timestamps();

            $table->unique(['rateio_central_id', 'unidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rateio_unidades');
    }
};
