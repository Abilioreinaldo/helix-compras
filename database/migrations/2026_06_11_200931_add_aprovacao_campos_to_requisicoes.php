<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->unsignedInteger('ciclo_aprovacao')->default(1)->after('cotacao_concluida_em');
            $table->timestamp('aprovacao_iniciada_em')->nullable()->after('ciclo_aprovacao');
            $table->timestamp('aprovada_em')->nullable()->after('aprovacao_iniciada_em');
            $table->timestamp('reprovada_em')->nullable()->after('aprovada_em');
            $table->foreignId('reprovada_por')->nullable()->after('reprovada_em')
                ->constrained('users')->nullOnDelete();

            $table->index(['status', 'ciclo_aprovacao']);
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropIndex(['status', 'ciclo_aprovacao']);
            $table->dropConstrainedForeignId('reprovada_por');
            $table->dropColumn(['ciclo_aprovacao', 'aprovacao_iniciada_em', 'aprovada_em', 'reprovada_em']);
        });
    }
};
