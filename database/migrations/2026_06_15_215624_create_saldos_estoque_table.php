<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saldos_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->string('deposito', 255);
            // Identidade v1: texto original (exibição)
            $table->string('descricao_item', 500);
            // Identidade v1: normalizado = trim + lowercase + colapsa espaços (chave do UNIQUE)
            $table->string('descricao_normalizada', 500);
            $table->string('unidade_medida', 20)->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('custo_medio_ponderado', 15, 4)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['unidade_id', 'deposito', 'descricao_normalizada'], 'saldos_estoque_identidade_unique');
            $table->index('unidade_id');
            $table->index('deposito');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saldos_estoque');
    }
};
