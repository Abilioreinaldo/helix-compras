<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rateio mensal do custo da central por unidade. Um rateio por (mes, ano) —
     * UNIQUE garante idempotência no nível do banco. valor_total = custo central rateado.
     */
    public function up(): void
    {
        Schema::create('rateios_centrais', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('mes');   // 1..12
            $table->unsignedSmallInteger('ano');  // ex.: 2026
            $table->decimal('valor_total', 15, 2);
            $table->foreignId('criado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['mes', 'ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rateios_centrais');
    }
};
