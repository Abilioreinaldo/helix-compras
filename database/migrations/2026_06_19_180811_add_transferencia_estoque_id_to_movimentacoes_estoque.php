<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo de contexto (nullable, como os demais FKs de contexto): as duas movimentações
     * de uma transferência (saída na origem + entrada no destino) apontam para o registro.
     */
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->foreignId('transferencia_estoque_id')
                ->nullable()
                ->constrained('transferencias_estoque')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferencia_estoque_id');
        });
    }
};
