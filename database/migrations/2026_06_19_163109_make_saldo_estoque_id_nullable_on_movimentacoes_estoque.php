<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Torna saldo_estoque_id nullable: movimentações financeiras documentais (rateio da
     * central / desconto) não têm saldo de estoque. ->change() é driver-aware (Laravel gera
     * MODIFY no MySQL e rebuild no SQLite, preservando FK/índices).
     *
     * Ponto cego (validar em MySQL real): que o MODIFY mantém a FK saldos_estoque e o índice.
     */
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->unsignedBigInteger('saldo_estoque_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->unsignedBigInteger('saldo_estoque_id')->nullable(false)->change();
        });
    }
};
