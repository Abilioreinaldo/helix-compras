<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de catálogo de itens (global, identidade única via UUID).
     */
    public function up(): void
    {
        Schema::create('catalogo_itens', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('codigo', 255)->nullable();
            $table->string('descricao', 500);
            $table->string('unidade_medida', 20)->nullable();
            $table->string('categoria', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['codigo', 'deleted_at']);
            $table->index('descricao');
        });
    }

    /**
     * Remove a tabela de catálogo de itens.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_itens');
    }
};
