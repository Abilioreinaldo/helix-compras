<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->restrictOnDelete();
            // nullable: etapa pode ser sintética (emergencial sem correspondência em etapas_alcada)
            $table->foreignId('etapa_alcada_id')->nullable()->constrained('etapas_alcada')->restrictOnDelete();
            $table->unsignedInteger('ciclo')->default(1);
            $table->unsignedInteger('ordem');
            $table->string('nivel_exigido');                   // snapshot imutável
            $table->boolean('obrigatoria_emergencial')->default(false);
            $table->string('status')->default('pendente');     // pendente|aprovada|reprovada|pulada
            $table->foreignId('aprovador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('justificativa')->nullable();
            $table->timestamp('decidida_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['requisicao_id', 'ciclo', 'ordem', 'deleted_at']);
            $table->index(['requisicao_id', 'status']);
            $table->index(['requisicao_id', 'ciclo', 'ordem']);
            $table->index('aprovador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprovacoes');
    }
};
