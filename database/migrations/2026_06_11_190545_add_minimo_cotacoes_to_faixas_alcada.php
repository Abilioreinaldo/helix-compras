<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faixas_alcada', function (Blueprint $table) {
            $table->unsignedSmallInteger('minimo_cotacoes')->default(3)->after('is_emergencial');
        });
    }

    public function down(): void
    {
        Schema::table('faixas_alcada', function (Blueprint $table) {
            $table->dropColumn('minimo_cotacoes');
        });
    }
};
