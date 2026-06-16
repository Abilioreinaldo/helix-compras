<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log imutável (append-only) de fusões de saldos de estoque (v1.1-B).
     * Sem updated_at — padrão de log de auditoria do projeto.
     * Preserva snapshot completo do saldo origem ANTES da fusão.
     */
    public function up(): void
    {
        Schema::create('saldo_fusao_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_destino_id')->constrained('saldos_estoque')->restrictOnDelete();
            $table->foreignId('saldo_origem_id')->constrained('saldos_estoque')->restrictOnDelete();

            // Snapshot financeiro da origem antes da fusão
            $table->decimal('quantidade_origem', 15, 3);
            $table->decimal('cmp_origem', 15, 4);
            $table->decimal('valor_total_origem', 15, 2);

            // Snapshot de identidade da origem
            $table->foreignId('item_catalogo_id_origem')->nullable()->constrained('catalogo_itens')->restrictOnDelete();
            $table->string('descricao_normalizada_origem', 500);
            $table->string('deposito_origem', 255);
            $table->foreignId('unidade_id_origem')->constrained('unidades')->restrictOnDelete();

            // Executor
            $table->foreignId('executado_por')->constrained('users')->restrictOnDelete();

            // Apenas created_at — log imutável não tem updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index('saldo_destino_id');
            $table->index('saldo_origem_id');
        });
    }

    /**
     * Remove a tabela de log de fusão.
     */
    public function down(): void
    {
        Schema::dropIfExists('saldo_fusao_log');
    }
};
