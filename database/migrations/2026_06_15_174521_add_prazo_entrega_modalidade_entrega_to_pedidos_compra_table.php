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
        Schema::table('pedidos_compra', function (Blueprint $table) {
            $table->date('prazo_entrega')->nullable()->after('observacoes');
            $table->enum('modalidade_entrega', ['entrega', 'retirada', 'transportadora'])->nullable()->after('prazo_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_compra', function (Blueprint $table) {
            $table->dropColumn(['prazo_entrega', 'modalidade_entrega']);
        });
    }
};
