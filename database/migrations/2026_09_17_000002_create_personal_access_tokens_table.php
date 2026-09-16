<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tabela do Sanctum (tokens de API da identidade).
 *
 * O Compras não expõe API hoje, mas a identidade da fundação usa HasApiTokens e,
 * desde a v0.2.0, UserService::changeStatus/deleteUser/removeMembership REVOGAM os
 * tokens do usuário (helix/foundation UserService::revokeTokens). Sem a tabela,
 * inativar/excluir um usuário quebra com "no such table: personal_access_tokens".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
