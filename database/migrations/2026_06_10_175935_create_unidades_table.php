<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de unidades da rede Comendador.
     */
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo');
            $table->string('cnpj', 14)->nullable()->unique();
            $table->string('endereco')->nullable();
            $table->foreignId('gestor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('ativa');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Remove a tabela de unidades.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
