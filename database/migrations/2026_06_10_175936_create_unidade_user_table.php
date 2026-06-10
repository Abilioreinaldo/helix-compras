<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela pivot de vínculo entre usuários e unidades com perfil e alçada.
     */
    public function up(): void
    {
        Schema::create('unidade_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('perfil');
            $table->string('nivel_alcada')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'unidade_id', 'perfil']);
            $table->index(['unidade_id', 'perfil']);
            $table->index(['unidade_id', 'nivel_alcada']);
        });
    }

    /**
     * Remove a tabela pivot.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidade_user');
    }
};
