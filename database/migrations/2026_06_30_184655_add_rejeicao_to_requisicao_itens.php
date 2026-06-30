<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicao_itens', function (Blueprint $table) {
            // Decisão por linha na aprovação: item rejeitado sai da compra (com motivo).
            $table->timestamp('rejeitado_em')->nullable()->after('avulso');
            $table->foreignId('rejeitado_por')->nullable()->after('rejeitado_em')->constrained('users')->nullOnDelete();
            $table->string('motivo_rejeicao', 500)->nullable()->after('rejeitado_por');
        });
    }

    public function down(): void
    {
        Schema::table('requisicao_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejeitado_por');
            $table->dropColumn(['rejeitado_em', 'motivo_rejeicao']);
        });
    }
};
