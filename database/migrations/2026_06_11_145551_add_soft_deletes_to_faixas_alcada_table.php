<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona soft delete à tabela de faixas de alçada.
     */
    public function up(): void
    {
        Schema::table('faixas_alcada', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Remove o soft delete da tabela de faixas de alçada.
     */
    public function down(): void
    {
        Schema::table('faixas_alcada', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
