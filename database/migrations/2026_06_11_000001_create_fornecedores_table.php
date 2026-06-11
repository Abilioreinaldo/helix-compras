<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de fornecedores (global, sem vínculo de unidade).
     */
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social', 255);
            $table->string('nome_fantasia', 255)->nullable();
            $table->string('cnpj', 14);
            $table->string('categoria', 60)->nullable();
            $table->string('contato_nome', 120)->nullable();
            $table->string('contato_email', 150)->nullable();
            $table->string('contato_telefone', 20)->nullable();
            $table->boolean('homologado')->default(false);
            $table->timestamp('homologado_em')->nullable();
            $table->foreignId('homologado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cnpj', 'deleted_at']);
            $table->index(['ativo', 'homologado']);
            $table->index('categoria');
            $table->index('razao_social');
        });
    }

    /**
     * Remove a tabela de fornecedores.
     */
    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
