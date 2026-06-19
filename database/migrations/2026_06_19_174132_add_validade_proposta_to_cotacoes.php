<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validade da proposta (data até a qual a cotação do fornecedor é válida).
     * Aditiva, nullable — cotações existentes ficam sem validade informada.
     */
    public function up(): void
    {
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->date('validade_proposta')->nullable()->after('prazo_entrega_dias');
        });
    }

    public function down(): void
    {
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->dropColumn('validade_proposta');
        });
    }
};
