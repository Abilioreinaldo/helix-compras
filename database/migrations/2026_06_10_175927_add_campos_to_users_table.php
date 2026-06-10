<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos de controle de acesso e soft delete à tabela users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->boolean('is_compradora')->default(false)->after('is_admin');
            $table->string('status')->default('ativo')->after('is_compradora');
            $table->boolean('precisa_trocar_senha')->default(false)->after('status');
            $table->softDeletes();
        });
    }

    /**
     * Reverte as colunas adicionadas.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_compradora', 'status', 'precisa_trocar_senha', 'deleted_at']);
        });
    }
};
