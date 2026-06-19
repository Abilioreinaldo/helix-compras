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

    /**
     * ATENÇÃO: o down() só é seguro num banco SEM movimentações de rateio/desconto (que têm
     * saldo_estoque_id null). Com elas presentes, voltar a NOT NULL falha (MySQL) ou zera
     * indevidamente — tratar como praticamente irreversível em produção após uso do rateio.
     */
    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->unsignedBigInteger('saldo_estoque_id')->nullable(false)->change();
        });
    }
};
