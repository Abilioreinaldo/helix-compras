<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de log de transições de status de requisições (imutável).
     */
    public function up(): void
    {
        Schema::create('requisicao_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->cascadeOnDelete();
            $table->string('status_anterior')->nullable();
            $table->string('status_novo');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->boolean('automatico')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['requisicao_id', 'created_at']);
        });
    }

    /**
     * Remove a tabela de logs de requisição.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicao_logs');
    }
};
