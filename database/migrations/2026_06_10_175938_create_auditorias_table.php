<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de auditoria imutável (log de eventos por campo).
     */
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('auditavel_type');
            $table->unsignedBigInteger('auditavel_id');
            $table->string('campo')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_novo')->nullable();
            $table->string('evento');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['auditavel_type', 'auditavel_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Remove a tabela de auditorias.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
