<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_reconciliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliacao_bancaria_id')->constrained('reconciliacoes_bancarias')->cascadeOnDelete();
            $table->string('numero_documento');
            $table->decimal('valor', 15, 2);
            $table->date('data_transacao')->nullable();
            $table->string('descricao')->nullable();
            // Pagamento vinculado quando houver match pela referência bancária.
            $table->foreignId('pagamento_id')->nullable()->constrained('pagamentos')->nullOnDelete();
            // pendente_match | conciliado | orfao
            $table->string('status')->default('pendente_match');
            $table->timestamps();

            $table->index('numero_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_reconciliacao');
    }
};
