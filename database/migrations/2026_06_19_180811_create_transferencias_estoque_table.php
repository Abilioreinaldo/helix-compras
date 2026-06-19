<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transferência de estoque entre unidades: saída no saldo de origem + entrada no saldo de
     * destino, num único registro rastreável. valor transferido pelo CMP vigente da origem.
     */
    public function up(): void
    {
        Schema::create('transferencias_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_origem_id')->constrained('saldos_estoque')->restrictOnDelete();
            $table->foreignId('saldo_destino_id')->constrained('saldos_estoque')->restrictOnDelete();
            $table->foreignId('unidade_destino_id')->constrained('unidades')->restrictOnDelete();
            $table->decimal('quantidade', 15, 3);
            $table->decimal('custo_unitario', 15, 4); // CMP da origem no momento da transferência
            $table->decimal('valor_total', 15, 2);
            $table->text('motivo')->nullable();
            $table->foreignId('executado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('saldo_origem_id');
            $table->index('saldo_destino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_estoque');
    }
};
