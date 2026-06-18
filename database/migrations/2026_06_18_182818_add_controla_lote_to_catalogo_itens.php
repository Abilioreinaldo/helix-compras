<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona controla_lote em catalogo_itens (Passo 0 / v1.1-C).
     *
     * Default false — itens existentes permanecem sem controle de lote, zero regressão.
     * O opt-in por item é feito via LigarControleLoteAction (Passo 1).
     */
    public function up(): void
    {
        Schema::table('catalogo_itens', function (Blueprint $table) {
            $table->boolean('controla_lote')->default(false)->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_itens', function (Blueprint $table) {
            $table->dropColumn('controla_lote');
        });
    }
};
