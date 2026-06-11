<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->timestamp('primeira_cotacao_em')->nullable()->after('triagem_iniciada_em');
            $table->timestamp('cotacao_concluida_em')->nullable()->after('primeira_cotacao_em');
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn(['primeira_cotacao_em', 'cotacao_concluida_em']);
        });
    }
};
