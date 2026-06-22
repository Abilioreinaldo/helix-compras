<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliacoes_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banco_id')->constrained('bancos')->restrictOnDelete();
            $table->date('data_arquivo');
            $table->integer('total_linhas')->default(0);
            $table->decimal('total_processado', 15, 2)->default(0);
            $table->decimal('total_conciliado', 15, 2)->default(0);
            // Hash do conteúdo do arquivo — previne reprocessar o mesmo extrato.
            $table->string('arquivo_hash')->unique();
            $table->foreignId('criado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliacoes_bancarias');
    }
};
