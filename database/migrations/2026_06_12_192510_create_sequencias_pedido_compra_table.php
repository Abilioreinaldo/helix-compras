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
        Schema::create('sequencias_pedido_compra', function (Blueprint $table) {
            $table->unsignedSmallInteger('ano')->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequencias_pedido_compra');
    }
};
