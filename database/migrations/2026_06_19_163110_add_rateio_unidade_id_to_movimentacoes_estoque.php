<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo de contexto (nullable, como item_recebimento_id / requisicao_material_id /
     * lote_estoque_id): a movimentação de rateio/desconto aponta para a linha de rateio_unidades,
     * dando unidade + mês/ano sem precisar de saldo de estoque.
     */
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->foreignId('rateio_unidade_id')
                ->nullable()
                ->constrained('rateio_unidades')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rateio_unidade_id');
        });
    }
};
